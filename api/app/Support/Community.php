<?php

namespace App\Support;

/** Operator values for the community surfaces, set on the admin's Settings page. */
final class Community
{
    /** The language of labels the bot writes in shared groups (a member's own words stay as written). */
    public static function labelLanguage(): string
    {
        $lang = RuntimeEnv::get(null, 'community_language', null, 'am');

        return in_array($lang, ['am', 'ti', 'en'], true) ? $lang : 'am';
    }

    public static function questionsGroup(): string
    {
        return RuntimeEnv::get(null, 'telegram_questions_group_id', null);
    }

    public static function goodsGroup(): string
    {
        return RuntimeEnv::get(null, 'telegram_goods_group_id', null);
    }

    /** A public t.me link to a message in a group, when the group has a username. */
    public static function groupLink(?string $groupUsername, ?int $messageId, ?int $threadId = null): ?string
    {
        if (! $groupUsername || ! $messageId) {
            return null;
        }

        return 'https://t.me/'.ltrim($groupUsername, '@').'/'.($threadId ? $threadId.'/' : '').$messageId;
    }

    public static function questionsPerDay(): int
    {
        return max(1, (int) RuntimeEnv::get(null, 'questions_per_day', null, '5'));
    }

    public static function assistantPerDay(): int
    {
        return max(0, (int) RuntimeEnv::get(null, 'assistant_per_day', null, '20'));
    }

    public static function consulatePhone(): string
    {
        return RuntimeEnv::get(null, 'safety_consulate_phone', null);
    }

    public static function mohrePhone(): string
    {
        return RuntimeEnv::get(null, 'safety_mohre_phone', null);
    }
}
