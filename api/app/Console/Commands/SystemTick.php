<?php

namespace App\Console\Commands;

use App\Http\Controllers\SystemTickController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * One run of the scheduler: under a lock, so two never overlap, and leaving
 * the heartbeat that /api/health and the Launch page read. The tick route
 * starts this in its own process, so no web request waits for it.
 */
class SystemTick extends Command
{
    /** Longer than any run should take; a run killed by a deploy frees the lock by itself. */
    public const LOCK_SECONDS = 300;

    protected $signature = 'system:tick';

    protected $description = 'Run the scheduler once, under a lock, and record the heartbeat';

    public function handle(): int
    {
        $lock = Cache::lock(SystemTickController::LOCK, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return self::SUCCESS;
        }

        $started = microtime(true);

        try {
            Artisan::call('schedule:run');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);

            return self::FAILURE;
        } finally {
            Cache::forever(SystemTickController::LAST_TICK_KEY, ['at' => now()->toIso8601String(), 'seconds' => round(microtime(true) - $started, 1)]);
            $lock->release();
        }
    }
}
