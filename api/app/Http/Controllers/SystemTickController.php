<?php

namespace App\Http\Controllers;

use App\Support\RuntimeEnv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

use function Illuminate\Support\php_binary;

/**
 * The scheduler's clock, driven from outside: this host runs no cron and no
 * queue worker, so the website's server calls this once a minute and it runs
 * `system:tick` (`schedule:run` under a lock, with the heartbeat the dashboard
 * reads). A run can take a minute. With several PHP workers (ServeWithWorkers) it
 * takes one and the others keep answering. On a single worker a run inside a request
 * would stop the bot and the website for as long as it lasted, so there it goes
 * to a process of its own; only there, because a process left running like that
 * ends up a child of whatever started the container, which may never collect it.
 *
 * It works with nothing set up. With no token saved it answers anyone, once per
 * minute of the clock: a caller can only make happen what that minute was going
 * to do anyway, and cannot use up the next one, in which something may be due.
 * A token saved on Settings (TICK_TOKEN on the website) closes it.
 */
class SystemTickController extends Controller
{
    public const LAST_TICK_KEY = 'system.tick.last';

    public const LOCK = 'system-tick';

    private const BUDGET_SECONDS = 90;

    public function __invoke(Request $request): JsonResponse
    {
        $expected = RuntimeEnv::get('services.host.tick_token', 'tick_token', 'TICK_TOKEN');

        if ($expected !== '') {
            // Header only: a token in the query string would sit in every proxy and access log.
            $provided = (string) $request->header('X-Tick-Token', '');

            if (strlen($expected) < 32 || ! hash_equals($expected, $provided)) {
                return response()->json(['error' => 'Invalid token.'], 401);
            }
        } elseif (! Cache::add('system-tick-open:'.now()->format('YmdHi'), true, 120)) {
            return response()->json(['skipped' => 'Asked too soon after the last tick.'], 429);
        }

        // Asked here only to spare the work; the run takes the lock for itself.
        $lock = Cache::lock(self::LOCK, 5);
        if (! $lock->get()) {
            return response()->json(['skipped' => 'A tick is already running.'], 409);
        }
        $lock->release();

        if ((int) getenv('PHP_CLI_SERVER_WORKERS') > 1) {
            return $this->runHere();
        }

        return $this->startInItsOwnProcess() ?? $this->runHere();
    }

    /** Null where a process cannot be started: the tests, a Windows machine, a host that forbids it. */
    private function startInItsOwnProcess(): ?JsonResponse
    {
        if (app()->runningUnitTests() || PHP_OS_FAMILY === 'Windows' || ! function_exists('proc_open')) {
            return null;
        }

        try {
            $process = Process::fromShellCommandline(escapeshellarg(php_binary()).' artisan system:tick > /dev/null 2>&1 < /dev/null &', base_path());
            $process->setTimeout(10)->disableOutput()->run();

            if (! $process->isSuccessful()) {
                return null;
            }
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        // The new process holds a copy of this connection until it ends, so the caller
        // cannot wait for the connection to close: the length says where the answer stops.
        $response = response()->json(['started' => true], 202);

        return $response->header('Content-Length', (string) strlen((string) $response->getContent()));
    }

    private function runHere(): JsonResponse
    {
        @set_time_limit(self::BUDGET_SECONDS + 15);
        ignore_user_abort(true);

        try {
            $failed = Artisan::call('system:tick') !== 0;
        } catch (\Throwable $e) {
            report($e);
            $failed = true;
        }

        // The details are in the log; a database or file error can name hosts and paths.
        return $failed
            ? response()->json(['error' => 'The scheduler run failed; see the application log.'], 500)
            : response()->json(['ok' => true]);
    }
}
