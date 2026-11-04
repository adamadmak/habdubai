<?php

namespace App\Http\Controllers;

use App\Support\SystemPulse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * For uptime monitors: is the API up, can it reach its database, and is the
 * scheduler keeping up. Numbers only; no setting, secret or address.
 *
 * Throttled here rather than by the throttle middleware: that keeps its
 * counters in the cache, which on this host is the database, so with the
 * database down the middleware itself would fail with a 500 and the monitor
 * would never see the 503 that says what is wrong.
 */
class HealthController extends Controller
{
    public const PER_MINUTE = 60;

    public function __invoke(Request $request): JsonResponse
    {
        $key = 'health:'.$request->ip();

        try {
            if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
                return response()->json(['message' => 'Too many requests.'], 429)
                    ->header('Retry-After', (string) RateLimiter::availableIn($key))
                    ->header('Cache-Control', 'no-store');
            }
            RateLimiter::hit($key, 60);
        } catch (\Throwable) {
            // The counters live in the database; with it down, answer anyway.
        }

        $db = SystemPulse::databaseUp();

        return response()->json([
            'ok' => $db,
            'db' => $db,
            'tick_age_seconds' => $db ? SystemPulse::tickAgeSeconds() : null,
            'queue_pending' => $db ? SystemPulse::queuePending() : 0,
            'failed_updates_24h' => $db ? SystemPulse::failedUpdates24h() : 0,
            // How many requests this server can answer at a time (see ServeWithWorkers).
            'workers' => max(1, (int) getenv('PHP_CLI_SERVER_WORKERS')),
        ], $db ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
