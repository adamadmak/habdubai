<?php

namespace App\Console\Commands;

use App\Bot\Home;
use App\Jobs\HandleTelegramUpdate;
use App\Models\TelegramUpdate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every minute: an update whose handler never finished (the web process died
 * during a deploy, a crash) is queued again, a few times at most; updates
 * older than a week are deleted, so nothing members sent the bot lingers.
 */
class UpdatesChores extends Command
{
    protected $signature = 'bot:updates-chores';

    protected $description = 'Retry Telegram updates left unhandled and delete week-old ones';

    /**
     * The database cache removes an expired row only when the same key is asked for again, so
     * the keys that are never asked for twice (a rate limit per address, a one-time ticket)
     * would stay for good. Once an hour they go.
     */
    private function pruneCache(): void
    {
        if (config('cache.default') !== 'database' || ! Cache::add('cache-pruned', true, 3600)) {
            return;
        }

        DB::table((string) config('cache.stores.database.table', 'cache'))->where('expiration', '<=', now()->timestamp)->delete();
    }

    public function handle(Home $home): int
    {
        if (! Schema::hasColumn('telegram_updates', 'attempts')) {
            $this->info('telegram_updates.attempts not migrated yet.');

            return self::SUCCESS;
        }

        $retried = 0;
        TelegramUpdate::query()
            ->whereNull('processed_at')
            ->where('attempts', '<', TelegramUpdate::MAX_ATTEMPTS)
            ->whereBetween('received_at', [now()->subDay(), now()->subMinutes(2)])
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (TelegramUpdate $update) use (&$retried): void {
                // Once per five minutes per update, however long the queue takes to reach it.
                if (Cache::add('telegram-update-retry:'.$update->id, true, now()->addMinutes(5))) {
                    HandleTelegramUpdate::dispatch($update->id, TelegramUpdate::orderKey($update->payload));
                    $retried++;
                }
            });

        $deleted = TelegramUpdate::query()->where('received_at', '<', now()->subDays(TelegramUpdate::KEEP_DAYS))->delete();

        $refreshed = $home->refreshChats();

        $this->pruneCache();

        $this->info("Queued {$retried} unhandled update(s) again, deleted {$deleted} old one(s), refreshed {$refreshed} member menu(s).");

        return self::SUCCESS;
    }
}
