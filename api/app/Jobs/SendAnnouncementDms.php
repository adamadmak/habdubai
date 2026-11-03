<?php

namespace App\Jobs;

use App\Announcements\AnnouncementPublisher;
use App\Bot\BotMessages;
use App\Models\Announcement;
use App\Models\Member;
use App\Services\TelegramBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a published announcement (or tomorrow's reminder) to every
 * subscriber of its topic, in their language. Runs on the queue, so a big
 * topic never holds the moderator's click; Telegram allows about thirty
 * messages a second, so it paces itself.
 */
class SendAnnouncementDms implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $announcementId, public readonly bool $reminder = false) {}

    public function handle(TelegramBotService $telegram, AnnouncementPublisher $publisher, BotMessages $messages): void
    {
        $a = Announcement::query()->with(['topic', 'member'])->find($this->announcementId);

        if (! $a || $a->status !== 'published') {
            return;
        }

        $sent = 0;

        $a->topic->subscribers()->orderBy('id')->chunkById(200, function ($members) use ($a, $telegram, $publisher, $messages, &$sent): void {
            foreach ($members as $member) {
                /** @var Member $member */
                if ($a->member_id === $member->id && ! $this->reminder) {
                    continue;
                }

                $text = $publisher->memberText($a, $member->language);
                if ($this->reminder) {
                    $text = $messages->text('ann_reminder', $member->language, ['card' => $text]);
                }

                try {
                    if ($a->photo_file_id && ! $this->reminder && mb_strlen($text) <= 1024) {
                        $telegram->sendPhotoById($member->chat_id, $a->photo_file_id, $text);
                    } else {
                        $telegram->sendText($member->chat_id, $text);
                    }
                    $sent++;
                } catch (\Throwable) {
                    // A member who blocked the bot; carry on with the rest.
                }

                if ($sent % 25 === 0) {
                    usleep(1_000_000);
                }
            }
        });

        if (! $this->reminder) {
            $a->forceFill(['dm_count' => $sent])->save();
        }
    }
}
