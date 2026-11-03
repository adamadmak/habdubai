<?php

namespace App\Support;

use App\Http\Controllers\SystemTickController;
use App\Models\TelegramUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The few numbers that say whether the API is alive and keeping up, shared by
 * the public health check and the admin's launch checklist. Each one answers
 * something (null or 0) rather than throw: they are read exactly when things
 * are going wrong.
 */
final class SystemPulse
{
    /** Is the database answering? */
    public static function databaseUp(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::connection()->select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Seconds since the last scheduler tick finished, or null when none ever has. */
    public static function tickAgeSeconds(): ?int
    {
        try {
            $at = Cache::get(SystemTickController::LAST_TICK_KEY)['at'] ?? null;

            return is_string($at) ? max(0, (int) CarbonImmutable::parse($at)->diffInSeconds(now(), true)) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Jobs waiting on the database queue (the tick drains it). */
    public static function queuePending(): int
    {
        try {
            if (config('queue.default') !== 'database') {
                return 0;
            }

            return DB::table((string) config('queue.connections.database.table', 'jobs'))->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Telegram updates from the last day whose handler failed and was not run again successfully. */
    public static function failedUpdates24h(): int
    {
        try {
            return TelegramUpdate::query()->whereNull('processed_at')->whereNotNull('error')
                ->where('received_at', '>=', now()->subDay())->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
