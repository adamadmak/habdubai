<?php

namespace App\Announcements;

use App\Bot\BotMessages;
use App\Jobs\SendAnnouncementDms;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Services\TelegramBotService;
use App\Support\JobSettings;
use Illuminate\Support\Facades\Log;

/**
 * Puts an announcement in the public channel, sends it to the topic's
 * subscribers, pins safety alerts for two days, and takes it down again.
 * The database status is the truth; the channel follows it.
 */
class AnnouncementPublisher
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
    ) {}

    /** @return int how many members will get it by DM */
    public function publish(Announcement $a, ?int $reviewerId = null): int
    {
        $a->loadMissing(['topic', 'member']);

        $a->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'reviewed_by' => $reviewerId ?? $a->reviewed_by,
        ])->save();

        $channel = JobSettings::channel();
        if ($channel !== '') {
            try {
                $text = $this->channelText($a);
                $message = $a->photo_file_id
                    ? $this->telegram->sendPhotoById($channel, $a->photo_file_id, mb_strlen($text) <= 1024 ? $text : $this->shortCard($a))
                    : $this->telegram->sendText($channel, $text);

                $a->forceFill(['channel_message_id' => $message['message_id'] ?? null])->save();

                if ($a->photo_file_id && mb_strlen($text) > 1024) {
                    $this->telegram->sendText($channel, $text);
                }

                if ($a->topic->pin && $a->channel_message_id) {
                    $this->telegram->pin($channel, (int) $a->channel_message_id);
                    $a->forceFill(['pinned_until' => now()->addHours(Announcement::PIN_HOURS)])->save();
                }
            } catch (\Throwable $e) {
                Log::warning('An announcement could not be posted to the channel.', ['announcement_id' => $a->id, 'error' => $e->getMessage()]);
            }
        }

        $count = $a->topic->subscribers()->count();
        SendAnnouncementDms::dispatch($a->id);

        AuditLog::record($reviewerId, 'announcements.published', 'announcement', $a->id, null, ['subscribers' => $count]);

        return $count;
    }

    public function delete(Announcement $a, ?int $actorUserId = null): void
    {
        $channel = JobSettings::channel();

        if ($channel !== '' && $a->channel_message_id) {
            if ($a->pinned_until) {
                $this->telegram->unpin($channel, (int) $a->channel_message_id);
            }
            $this->telegram->deleteMessage($channel, (int) $a->channel_message_id);
        }

        $a->forceFill(['status' => 'deleted', 'channel_message_id' => null, 'pinned_until' => null])->save();
        AuditLog::record($actorUserId, 'announcements.deleted', 'announcement', $a->id);
    }

    /** After an admin edit: re-render the channel message. */
    public function refresh(Announcement $a): void
    {
        $channel = JobSettings::channel();

        if ($channel === '' || ! $a->channel_message_id || $a->status !== 'published') {
            return;
        }

        $text = $this->channelText($a);
        $a->photo_file_id
            ? $this->telegram->editCaption($channel, (int) $a->channel_message_id, mb_strlen($text) <= 1024 ? $text : $this->shortCard($a))
            : $this->telegram->editText($channel, (int) $a->channel_message_id, $text);
    }

    /** The channel card: the topic, then the text in each card language. */
    public function channelText(Announcement $a): string
    {
        $languages = JobSettings::cardLanguages();
        $label = $languages[0];
        $texts = [];
        foreach ($languages as $l) {
            $t = trim($a->text($l));
            if ($t !== '' && ! in_array($t, $texts, true)) {
                $texts[] = $t;
            }
        }

        return $this->card($a, $label, implode("\n\n", array_map('e', $texts)));
    }

    /** One member's copy, in their language. */
    public function memberText(Announcement $a, string $language): string
    {
        return $this->card($a, $language, e($a->text($language)));
    }

    private function shortCard(Announcement $a): string
    {
        $label = JobSettings::cardLanguages()[0];

        return $this->card($a, $label, e(mb_substr($a->text($label), 0, 700)).'…');
    }

    private function card(Announcement $a, string $language, string $escapedText): string
    {
        $a->loadMissing(['topic', 'member']);

        $event = '';
        if ($a->event_date) {
            $event = $this->messages->text('ann_event_line', $language, [
                'date' => $a->event_date->format('D j M Y'),
                'place' => $a->event_place ? ' · 📍 '.e($a->event_place) : '',
            ]);
        }

        $card = $this->messages->text('ann_card', $language, [
            'emoji' => $a->topic->emoji,
            'topic' => e($a->topic->name($language)),
            'text' => $escapedText,
            'event' => $event,
            'poster' => e($a->posterName()),
        ]);

        if ($a->topic->key === 'fundraisers') {
            $card .= $this->messages->text('ann_fundraiser_note', $language);
        }

        return \App\Support\TelegramText::clip($card, 4000);
    }
}
