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
        $tried = 0;
        $failedInRow = 0;

        $a->topic->subscribers()->orderBy('id')->chunkById(200, function ($members) use ($a, $telegram, $publisher, $messages, &$sent, &$tried, &$failedInRow): bool {
            foreach ($members as $member) {
                /** @var Member $member */
                if ($a->member_id === $member->id && ! $this->reminder) {
                    continue;
                }

                $text = $publisher->memberText($a, $member->language);
                if ($this->reminder) {
                    $text = $messages->text('ann_reminder', $member->language, ['card' => $text]);
                }

                $send = fn () => $a->photo_file_id && ! $this->reminder && mb_strlen($text) <= 1024
                    ? $telegram->sendPhotoById($member->chat_id, $a->photo_file_id, $text)
                    : $telegram->sendText($member->chat_id, $text);

                try {
                    try {
                        $send();
                    } catch (\RuntimeException $e) {
                        // Telegram asked us to slow down: wait as long as it says, once.
                        if (! preg_match('/retry after (\d+)/i', $e->getMessage(), $m)) {
                            throw $e;
                        }
                        sleep(min(30, (int) $m[1]));
                        $send();
                    }
                    $sent++;
                    $failedInRow = 0;
                } catch (\Throwable $e) {
                    // No token, or a revoked one: nobody will receive anything, so stop now.
                    if (preg_match('/not configured|unauthorized/i', $e->getMessage())) {
                        return false;
                    }
                    // A member who blocked the bot is normal; fifty failures in a row means Telegram is down.
                    if (++$failedInRow >= 50) {
                        return false;
                    }
                }

                // About thirty messages a second is Telegram's limit; count every try, not only successes.
                if (++$tried % 25 === 0) {
                    usleep(1_000_000);
                }
            }

            return true;
        });

        if (! $this->reminder) {
            $a->forceFill(['dm_count' => $sent])->save();
        }
    }
}
