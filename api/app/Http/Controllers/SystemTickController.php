<?php

namespace App\Http\Controllers;

use App\Support\RuntimeEnv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * The scheduler's clock, driven from outside: this host runs no cron and no
 * queue worker, so an external timer calls this once a minute with
 * TICK_TOKEN and it runs `schedule:run` (which includes the queue worker).
 * One run at a time under a cache lock, a time budget because it holds a
 * web worker, and a heartbeat the dashboard reads.
 */
class SystemTickController extends Controller
{
    public const LAST_TICK_KEY = 'system.tick.last';

    private const BUDGET_SECONDS = 90;

    public function __invoke(Request $request): JsonResponse
    {
        $expected = RuntimeEnv::get('services.host.tick_token', 'tick_token', 'TICK_TOKEN');
        $provided = (string) ($request->header('X-Tick-Token') ?? $request->query('token', ''));

        if (strlen($expected) < 32 || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Invalid token.'], 401);
        }

        $lock = Cache::lock('system-tick', self::BUDGET_SECONDS + 30);
        if (! $lock->get()) {
            return response()->json(['skipped' => 'A tick is already running.'], 409);
        }

        $started = microtime(true);
        @set_time_limit(self::BUDGET_SECONDS + 15);
        ignore_user_abort(true);

        try {
            Artisan::call('schedule:run');
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            report($e);

            // The details are in the log; a database or file error can name hosts and paths.
            return response()->json(['error' => 'The scheduler run failed; see the application log.'], 500);
        } finally {
            $seconds = round(microtime(true) - $started, 1);
            Cache::forever(self::LAST_TICK_KEY, ['at' => now()->toIso8601String(), 'seconds' => $seconds]);
            $lock->release();
        }

        return response()->json(['ok' => true, 'seconds' => $seconds, 'output' => mb_substr($output, 0, 2000)]);
    }
}
