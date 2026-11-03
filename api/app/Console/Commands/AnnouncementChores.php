<?php

namespace App\Console\Commands;

use App\Jobs\SendAnnouncementDms;
use App\Models\Announcement;
use App\Services\TelegramBotService;
use App\Support\JobSettings;
use Illuminate\Console\Command;

/** Unpins safety alerts after 48 hours and sends "tomorrow" reminders for events. */
class AnnouncementChores extends Command
{
    protected $signature = 'announcements:chores {--reminders : also send reminders for events tomorrow}';

    protected $description = 'Unpin expired pinned announcements, and send event reminders';

    public function handle(TelegramBotService $telegram): int
    {
        $channel = JobSettings::channel();

        $expired = Announcement::query()->whereNotNull('pinned_until')->where('pinned_until', '<=', now())->get();
        foreach ($expired as $a) {
            if ($channel !== '' && $a->channel_message_id) {
                $telegram->unpin($channel, (int) $a->channel_message_id);
            }
            $a->forceFill(['pinned_until' => null])->save();
        }

        $reminded = 0;
        if ($this->option('reminders')) {
            $tomorrow = now('Asia/Dubai')->addDay()->toDateString();
            $due = Announcement::query()->where('status', 'published')->whereDate('event_date', $tomorrow)->whereNull('reminder_sent_at')->get();
            foreach ($due as $a) {
                $a->forceFill(['reminder_sent_at' => now()])->save();
                SendAnnouncementDms::dispatch($a->id, true);
                $reminded++;
            }
        }

        $this->info("Unpinned {$expired->count()}, reminders for {$reminded} event(s).");

        return self::SUCCESS;
    }
}
