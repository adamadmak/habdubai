<?php

namespace App\Bot;

use App\Models\AuditLog;
use App\Models\GroupReport;
use App\Models\Member;
use App\Models\MemberStrike;
use App\Services\TelegramBotService;
use App\Support\Community;
use App\Support\JobSettings;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The first pass on every message in the community groups, before anything
 * else reads it. Non-members are removed; new members cannot post links or
 * phone numbers for a week; known scam phrases and banned goods items are
 * taken down; three automatic removals in a day mute a member for 24 hours.
 * Leaders, moderators and admins are never touched. Every removal is
 * explained to the member in a private message.
 *
 * It also handles /report, sent as a reply to the message being reported.
 */
class GroupModerator
{
    public const NEW_MEMBER_DAYS = 7;

    public const REMOVALS_BEFORE_MUTE = 3;

    public const AUTO_MUTE_HOURS = 24;

    public const WARNINGS_BEFORE_MUTE = 3;

    public const WARNING_MUTE_DAYS = 7;

    /**
     * What the goods board never carries, as regex fragments matched at the
     * start of a word (so "jewel" also catches "jewellery"). Checked in the
     * goods group and in every goods post a member writes.
     */
    public const BANNED_ITEMS = [
        'cash(?!ew)', 'gold', 'jewel', 'medicine', 'pills', 'drugs', 'weapon', 'cigarette', 'alcohol', 'khat',
        'ወርቅ', 'ወርቂ', 'ጌጣጌጥ', 'ስልማት', 'መድሃኒት', 'መድኃኒት', 'ጫት',
    ];

    public static function bannedItemIn(string $text): bool
    {
        $lower = mb_strtolower($text);

        foreach (self::BANNED_ITEMS as $item) {
            if (preg_match('/(?<![\p{L}])'.$item.'/u', $lower)) {
                return true;
            }
        }

        return false;
    }

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
    ) {}

    public static function isCommunityGroup(string $chatId): bool
    {
        return $chatId !== '' && in_array($chatId, array_filter([Community::questionsGroup(), Community::goodsGroup()]), true);
    }

    /**
     * An edited message is judged the same way, except that an edit never files
     * a /report a second time.
     *
     * @return bool true when the message was removed (nothing else should read it)
     */
    public function check(array $message, bool $edited = false): bool
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $messageId = (int) ($message['message_id'] ?? 0);

        // The group's own anonymous admins, its linked channel, and other bots.
        if (isset($message['sender_chat']) || ! empty($message['is_automatic_forward']) || ! empty($message['from']['is_bot'])) {
            return false;
        }

        // Members joining or leaving: nothing to judge.
        if (isset($message['new_chat_members']) || isset($message['left_chat_member'])) {
            return false;
        }

        $member = Member::findByTelegramUser((int) ($message['from']['id'] ?? 0));
        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));

        if (! $member || ! $member->isActive()) {
            $this->telegram->deleteMessage($chatId, $messageId);
            $member?->isBanned()
                ? $this->telegram->banFromChat($chatId, (int) $message['from']['id'])
                : $this->telegram->kickFromChat($chatId, (int) $message['from']['id']);

            return true;
        }

        if (! $edited && preg_match('#^/report(@\w+)?\b#i', $text)) {
            $this->report($member, $message);

            return true;
        }

        if ($member->isTrusted()) {
            return false;
        }

        if ($member->isMuted()) {
            $this->telegram->deleteMessage($chatId, $messageId);

            return true;
        }

        $reason = $this->violation($member, $text, $chatId, self::hasHiddenLink($message));

        if ($reason === null) {
            return false;
        }

        $this->telegram->deleteMessage($chatId, $messageId);
        MemberStrike::record($member, 'removed', $reason, (int) $chatId);
        $this->tell($member, $reason);

        $today = MemberStrike::query()->where('member_id', $member->id)->where('kind', 'removed')->where('created_at', '>=', now()->subDay())->count();
        if ($today >= self::REMOVALS_BEFORE_MUTE) {
            $this->mute($member, now()->addHours(self::AUTO_MUTE_HOURS), 'Automatic: '.$today.' removals in a day.');
        }

        return true;
    }

    /** The text key explaining the removal, or null when the message is fine. */
    private function violation(Member $member, string $text, string $chatId, bool $hiddenLink = false): ?string
    {
        if ($text === '') {
            return null;
        }

        $lower = mb_strtolower($text);
        $isNew = $member->verified_at === null || $member->verified_at->greaterThan(now()->subDays(self::NEW_MEMBER_DAYS));

        if ($isNew && ($hiddenLink || preg_match('#(https?://|www\.|t\.me/|\b[a-z0-9-]+\.(com|net|org|ae|et|io|me)\b)#i', $text) || preg_match('/\+?\d(?:[\s-]?\d){8,14}(?![\d])/', $text))) {
            return 'mod_removed_link';
        }

        foreach (JobSettings::scamPhrases() as $phrase) {
            if ($phrase !== '' && str_contains($lower, $phrase)) {
                return 'mod_removed_phrase';
            }
        }

        if ($chatId === Community::goodsGroup() && self::bannedItemIn($text)) {
            return 'mod_removed_item';
        }

        return null;
    }

    /**
     * A link or number Telegram found in the message, including one hidden behind
     * ordinary words ("apply here") that the text alone does not show.
     */
    private static function hasHiddenLink(array $message): bool
    {
        foreach (array_merge($message['entities'] ?? [], $message['caption_entities'] ?? []) as $entity) {
            if (in_array($entity['type'] ?? '', ['url', 'text_link', 'phone_number', 'email'], true)) {
                return true;
            }
        }

        return false;
    }

    private function report(Member $reporter, array $message): void
    {
        $chatId = (int) $message['chat']['id'];
        $this->telegram->deleteMessage($chatId, (int) $message['message_id']);

        $target = $message['reply_to_message'] ?? null;
        // A reply to the topic's own first message is not a reply to anyone's message.
        if (! is_array($target) || ! empty($target['forum_topic_created']) || ($target['message_id'] ?? null) === ($message['message_thread_id'] ?? null)) {
            $this->tell($reporter, 'mod_report_need_reply');

            return;
        }

        $reported = isset($target['from']['id']) ? Member::findByTelegramUser((int) $target['from']['id']) : null;
        $targetId = (int) $target['message_id'];

        try {
            GroupReport::query()->create([
                'reporter_id' => $reporter->id,
                'chat_id' => $chatId,
                'message_id' => $targetId,
                'reported_member_id' => $reported?->id,
                'excerpt' => mb_substr((string) ($target['text'] ?? $target['caption'] ?? '[media]'), 0, 1000),
                'status' => 'open',
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->tell($reporter, 'mod_report_thanks');

            return;
        }

        $count = GroupReport::query()->where('chat_id', $chatId)->where('message_id', $targetId)->where('status', 'open')->count();

        if ($count >= GroupReport::HIDE_THRESHOLD && ! $reported?->isTrusted()) {
            $this->telegram->deleteMessage($chatId, $targetId);
            GroupReport::query()->where('chat_id', $chatId)->where('message_id', $targetId)->where('status', 'open')->update(['action' => 'hidden']);
            AuditLog::record(null, 'moderation.hidden_by_reports', 'member', $reported?->id, null, ['chat_id' => $chatId, 'message_id' => $targetId, 'reports' => $count]);
        }

        $this->tell($reporter, 'mod_report_thanks');
    }

    // ---- actions shared with the moderator queue -------------------------------

    public function warn(Member $member, string $reason, ?int $actorId): void
    {
        MemberStrike::record($member, 'warning', $reason, null, null, $actorId);
        $this->tell($member, 'mod_warning', ['reason' => e($reason)]);

        $recent = MemberStrike::query()->where('member_id', $member->id)->where('kind', 'warning')->where('created_at', '>=', now()->subDays(90))->count();
        if ($recent >= self::WARNINGS_BEFORE_MUTE) {
            $this->mute($member, now()->addDays(self::WARNING_MUTE_DAYS), $recent.' warnings in 90 days.', $actorId);
        }
    }

    public function mute(Member $member, \DateTimeInterface $until, string $reason, ?int $actorId = null): void
    {
        $member->forceFill(['muted_until' => $until])->save();
        MemberStrike::record($member, 'mute', $reason, null, $until, $actorId);

        foreach (array_filter([Community::questionsGroup(), Community::goodsGroup()]) as $group) {
            $this->telegram->mute($group, (int) $member->telegram_user_id, $until);
        }

        $hours = (int) ceil(now()->diffInMinutes($until) / 60);
        $this->tell($member, 'mod_muted', ['hours' => $hours]);
        AuditLog::record($actorId, 'moderation.muted', 'member', $member->id, null, ['until' => $until->format(DATE_ATOM), 'reason' => $reason]);
    }

    private function tell(Member $member, string $key, array $vars = []): void
    {
        if (! $member->chat_id) {
            return;
        }

        try {
            $this->telegram->sendText($member->chat_id, $this->messages->text($key, $member->language, $vars));
        } catch (\Throwable) {
            // The member may have blocked the bot; the action stands.
        }
    }
}
