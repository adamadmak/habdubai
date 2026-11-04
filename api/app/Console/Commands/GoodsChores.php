<?php

namespace App\Console\Commands;

use App\Bot\GoodsFlow;
use App\Models\Rating;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Upkeep for the goods board, every few minutes and only what is due: posts
 * past their date close, the day after a flight both sides are asked whether
 * the parcel arrived (only in Dubai waking hours), and ratings older than a
 * week become visible.
 */
class GoodsChores extends Command
{
    protected $signature = 'goods:chores {--any-hour : send check-ins whatever the time in Dubai}';

    protected $description = 'Expire goods posts, send arrival check-ins, reveal week-old ratings';

    public function handle(GoodsFlow $goods): int
    {
        // This host does not migrate on deploy; until the goods tables exist there is nothing to do.
        if (! Schema::hasTable('goods_matches') || ! Schema::hasTable('ratings')) {
            $this->info('Goods tables not migrated yet.');

            return self::SUCCESS;
        }

        $expired = $goods->expire();

        $hour = (int) now('Asia/Dubai')->format('G');
        $awake = $this->option('any-hour') || ($hour >= 9 && $hour < 21);
        $checkins = $awake ? $goods->sendCheckins() : 0;
        $reminders = $awake ? $goods->sendRatingReminders() : 0;

        $revealed = Rating::revealDue();

        $this->info("Expired {$expired} post(s), sent {$checkins} check-in(s) and {$reminders} rating reminder(s), revealed ratings for {$revealed} member(s).");

        return self::SUCCESS;
    }
}
