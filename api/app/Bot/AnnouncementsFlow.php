<?php

namespace App\Bot;

use App\Ai\Translator;
use App\Announcements\AnnouncementPublisher;
use App\Models\Announcement;
use App\Models\AnnouncementTopic;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Member;
use App\Services\TelegramBotService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * /announce for leaders, moderators and admins: topic → text in one language
 * → optional photo → optional date and place → a translated preview →
 * publish (or wait for a moderator on topics that need approval).
 * /topics lets every member choose which topics reach them as a message.
 */
class AnnouncementsFlow
{
    public const STATES = ['ann_topic', 'ann_text', 'ann_photo', 'ann_date', 'ann_place', 'ann_confirm'];

    /** Announcements one leader may send in a day. */
    public const LEADER_PER_DAY = 5;

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
        private readonly Translator $translator,
        private readonly AnnouncementPublisher $publisher,
    ) {}

    public function begin(Member $member, Conversation $conversation): void
    {
        $l = $member->language;
        $topics = AnnouncementTopic::postableBy($member);

        if ($topics->isEmpty()) {
            $this->say($member, 'ann_no_rights');

            return;
        }

        // Each announcement can reach every subscriber's private chat: a leader posts a few a
        // day at most. Moderators and admins are not counted.
        if (! $member->hasRole('moderator', 'admin')) {
            // A Dubai day; a rejected draft does not use up a turn.
            $today = Announcement::query()->where('member_id', $member->id)->whereIn('status', ['pending', 'published'])
                ->where('created_at', '>=', now('Asia/Dubai')->startOfDay()->utc())->count();
            if ($today >= self::LEADER_PER_DAY) {
                $this->say($member, 'ann_limit', ['count' => $today]);

                return;
            }
        }

        $conversation->forceFill(['state' => 'ann_topic', 'data' => []])->save();
        $rows = array_chunk($topics->map(fn (AnnouncementTopic $t) => $t->label($l))->all(), 2);
        $rows[] = [$this->messages->button('btn_cancel', $l)];
        $this->telegram->sendText($member->chat_id, $this->messages->text('ann_pick_topic', $l), $this->telegram->replyKeyboard($rows, oneTime: false));
    }

    public function handle(Member $member, Conversation $conversation, Incoming $in): void
    {
        $l = $member->language;
        $text = trim((string) ($in->text ?? $in->message['caption'] ?? ''));
        $skip = $this->messages->pressed('btn_skip', $text);

        switch ($conversation->state) {
            case 'ann_topic':
                $topic = AnnouncementTopic::postableBy($member)->first(fn (AnnouncementTopic $t) => in_array($text, array_map(fn (string $l) => $t->label($l), Choices::LANGUAGES), true));
                if (! $topic) {
                    $this->begin($member, $conversation);

                    return;
                }
                $conversation->moveTo('ann_text', ['topic_id' => $topic->id]);
                $this->telegram->sendText($member->chat_id, $this->messages->text('ann_ask_text', $l), $this->cancelOnly($l));

                return;

            case 'ann_text':
                if (mb_strlen($text) < 10 || mb_strlen($text) > 2000) {
                    $this->say($member, 'ann_text_invalid');

                    return;
                }
                $conversation->moveTo('ann_photo', ['text' => $text]);
                $this->telegram->sendText($member->chat_id, $this->messages->text('ann_ask_photo', $l), $this->skipKeyboard($l));

                return;

            case 'ann_photo':
                $photo = $in->photoFileId();
                if ($photo === null && ! $skip) {
                    $this->telegram->sendText($member->chat_id, $this->messages->text('ann_ask_photo', $l), $this->skipKeyboard($l));

                    return;
                }
                $conversation->moveTo('ann_date', ['photo' => $photo]);
                $this->telegram->sendText($member->chat_id, $this->messages->text('ann_ask_date', $l), $this->skipKeyboard($l));

                return;

            case 'ann_date':
                if ($skip) {
                    $conversation->moveTo('ann_confirm', ['date' => null, 'place' => null]);
                    $this->preview($member, $conversation);

                    return;
                }
                $date = self::parseDate($text);
                if ($date === null) {
                    $this->telegram->sendText($member->chat_id, $this->messages->text('ann_date_invalid', $l), $this->skipKeyboard($l));

                    return;
                }
                $conversation->moveTo('ann_place', ['date' => $date->toDateString()]);
                $this->telegram->sendText($member->chat_id, $this->messages->text('ann_ask_place', $l), $this->skipKeyboard($l));

                return;

            case 'ann_place':
                // A sticker or a photo here is no place: none rather than an empty one.
                $conversation->moveTo('ann_confirm', ['place' => $skip || $text === '' ? null : mb_substr($text, 0, 160)]);
                $this->preview($member, $conversation);

                return;

            case 'ann_confirm':
                if (! $this->messages->pressed('btn_publish', $text)) {
                    $this->preview($member, $conversation);

                    return;
                }
                $this->submit($member, $conversation);

                return;
        }
    }

    private function preview(Member $member, Conversation $conversation): void
    {
        $l = $member->language;
        $data = $conversation->data ?? [];

        if (! isset($data['translated'])) {
            $this->telegram->typing($member->chat_id);
            // The translator keeps the original wording for the language it was written in.
            $data['translated'] = $this->translator->toAll((string) $data['text']);
            $conversation->forceFill(['data' => $data])->save();
        }

        $draft = $this->draft($member, $data);
        $topic = AnnouncementTopic::query()->find($data['topic_id']);
        $needsApproval = $topic && $topic->needs_approval && ! $member->hasRole('moderator', 'admin');

        $this->telegram->sendText($member->chat_id, $this->messages->text('ann_preview', $l, [
            'card' => $this->publisher->memberText($draft, $l),
            'note' => $needsApproval ? $this->messages->text('ann_needs_approval_note', $l) : '',
        ]), $this->telegram->replyKeyboard([[$this->messages->button('btn_publish', $l), $this->messages->button('btn_cancel', $l)]]));
    }

    private function submit(Member $member, Conversation $conversation): void
    {
        $data = $conversation->data ?? [];
        $conversation->reset();

        // Checked again at the moment of posting: a moderator may have taken the leader's
        // right to this topic away, or switched the topic off, since the flow began.
        if (! AnnouncementTopic::postableBy($member)->contains('id', (int) ($data['topic_id'] ?? 0)) || trim((string) ($data['text'] ?? '')) === '') {
            $this->say($member, 'ann_no_rights', [], $this->messages->menuKeyboard($member->language));

            return;
        }

        $announcement = $this->draft($member, $data);
        $needsApproval = $announcement->topic->needs_approval && ! $member->hasRole('moderator', 'admin');
        $announcement->status = 'pending';
        $announcement->save();
        AuditLog::record(null, 'announcements.submitted', 'announcement', $announcement->id, null, ['member_id' => $member->id]);

        if ($needsApproval) {
            $this->say($member, 'ann_pending', [], $this->messages->menuKeyboard($member->language));

            return;
        }

        $count = $this->publisher->publish($announcement);
        $this->telegram->sendText($member->chat_id, $this->messages->text('ann_published', $member->language, ['count' => $count]), [
            'inline_keyboard' => [[['text' => $this->messages->button('btn_ann_delete', $member->language), 'callback_data' => 'an:del:'.$announcement->id]]],
        ]);
    }

    private function draft(Member $member, array $data): Announcement
    {
        $translated = $data['translated'] ?? ['am' => $data['text'], 'ti' => $data['text'], 'en' => $data['text'], 'om' => $data['text']];

        $announcement = new Announcement([
            'topic_id' => $data['topic_id'],
            'member_id' => $member->id,
            'original_language' => $member->language,
            'text_am' => $translated['am'] ?? $data['text'],
            'text_ti' => $translated['ti'] ?? $data['text'],
            'text_en' => $translated['en'] ?? $data['text'],
            'text_om' => $translated['om'] ?? $data['text'],
            'photo_file_id' => $data['photo'] ?? null,
            'event_date' => $data['date'] ?? null,
            'event_place' => $data['place'] ?? null,
        ]);
        $announcement->setRelation('member', $member);
        $announcement->setRelation('topic', AnnouncementTopic::query()->findOrFail($data['topic_id']));

        return $announcement;
    }

    /** 🗑 under a published announcement: its poster, a moderator or an admin may delete it. */
    public function deleteButton(Member $member, Callback $cb, int $announcementId): void
    {
        $a = Announcement::query()->find($announcementId);

        if (! $a || $a->status !== 'published' || ($a->member_id !== $member->id && ! $member->hasRole('moderator', 'admin'))) {
            $this->telegram->answerCallback($cb->id);

            return;
        }

        $this->publisher->delete($a);
        if ($cb->messageId) {
            $this->telegram->editText($member->chat_id, $cb->messageId, $this->messages->text('ann_deleted', $member->language));
        }
        $this->telegram->answerCallback($cb->id, $this->messages->text('ann_deleted', $member->language));
    }

    // ---- /topics ----------------------------------------------------------------

    public function topics(Member $member, ?int $editMessageId = null): void
    {
        $l = $member->language;

        $buttons = AnnouncementTopic::listed()->map(fn (AnnouncementTopic $t) => [
            'text' => ($t->isSubscribed($member) ? '✅ ' : '⬜ ').$t->label($l),
            'callback_data' => 'tp:'.$t->id,
        ])->all();

        $keyboard = ['inline_keyboard' => array_chunk($buttons, 2)];

        if ($editMessageId && $this->telegram->editText($member->chat_id, $editMessageId, $this->messages->text('topics_menu', $l), $keyboard)) {
            return;
        }

        $this->telegram->sendText($member->chat_id, $this->messages->text('topics_menu', $l), $keyboard);
    }

    public function toggleTopic(Member $member, Callback $cb, int $topicId): void
    {
        $topic = AnnouncementTopic::query()->where('active', true)->find($topicId);

        if ($topic) {
            DB::table('member_topic_prefs')->updateOrInsert(
                ['member_id' => $member->id, 'topic_id' => $topic->id],
                ['subscribed' => ! $topic->isSubscribed($member)]
            );
        }

        $this->topics($member, $cb->messageId);
        $this->telegram->answerCallback($cb->id);
    }

    // ---- helpers ------------------------------------------------------------------

    public static function parseDate(string $text): ?CarbonImmutable
    {
        $text = trim($text);

        foreach (['Y-m-d', 'd/m/Y', 'd.m.Y', 'd-m-Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $text);
            } catch (\Throwable) {
                continue;
            }
            if ($date !== false && $date->format($format) === $text) {
                return $date->greaterThanOrEqualTo(now()->startOfDay()) && $date->lessThan(now()->addYears(2)) ? $date : null;
            }
        }

        return null;
    }

    private function cancelOnly(string $l): array
    {
        return $this->telegram->replyKeyboard([[$this->messages->button('btn_cancel', $l)]], oneTime: false);
    }

    private function skipKeyboard(string $l): array
    {
        return $this->telegram->replyKeyboard([[$this->messages->button('btn_skip', $l), $this->messages->button('btn_cancel', $l)]], oneTime: false);
    }

    private function say(Member $member, string $key, array $vars = [], ?array $markup = null): void
    {
        $this->telegram->sendText($member->chat_id, $this->messages->text($key, $member->language, $vars), $markup);
    }
}
