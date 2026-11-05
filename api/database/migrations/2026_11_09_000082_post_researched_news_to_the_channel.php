<?php

use App\Announcements\AnnouncementPublisher;
use App\Models\Announcement;
use Illuminate\Database\Migrations\Migration;

/**
 * The owner asked on 5 Nov 2026 for the researched news (000080) to go to the channel too.
 * This host has no shell, so a migration does it, once: oldest first so the newest
 * ends up at the foot of the channel, and only an item not on the channel yet.
 * Channel only: no messages to members, no pins, dates unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $publisher = app(AnnouncementPublisher::class);
        $texts = array_column(require database_path('data/news_2026_11.php'), 'en');

        Announcement::query()->whereIn('text_en', $texts)->where('status', 'published')->whereNull('channel_message_id')
            ->orderBy('published_at')->get()
            ->each(function (Announcement $announcement) use ($publisher): void {
                $publisher->postToChannelOnly($announcement);
                // Telegram allows a channel about twenty messages a minute.
                sleep(4);
            });
    }

    public function down(): void
    {
        // A post on a public channel is not taken back by a rollback.
    }
};
