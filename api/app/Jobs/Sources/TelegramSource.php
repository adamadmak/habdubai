<?php

namespace App\Jobs\Sources;

use App\Models\JobSource;
use RuntimeException;

/**
 * A Telegram channel or group the bot is a member of. Nothing is polled:
 * Telegram pushes each post to the webhook, and the router hands it to
 * JobIngest. Moderator forwards use the built-in "forwarded" source.
 */
class TelegramSource implements SourceFetcher
{
    public function fetch(JobSource $source): array
    {
        throw new RuntimeException('Telegram sources are pushed by Telegram; there is nothing to fetch.');
    }

    /** One post (channel post, group message or forward) as a listing. */
    public static function listing(array $message, string $keyPrefix): ?RawListing
    {
        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));

        if (mb_strlen($text) < 30) {
            return null;
        }

        $chat = $message['forward_origin']['chat'] ?? $message['forward_from_chat'] ?? $message['chat'] ?? [];
        $messageId = $message['forward_origin']['message_id'] ?? $message['forward_from_message_id'] ?? $message['message_id'] ?? null;
        $username = $chat['username'] ?? null;
        $url = $username && $messageId ? 'https://t.me/'.$username.'/'.$messageId : null;

        $firstLine = RawListing::clean(strtok($text, "\n") ?: $text, 300);

        return new RawListing(
            externalKey: mb_substr($keyPrefix.':'.($url ?? sha1($text)), 0, 191),
            text: RawListing::clean($text, 8000) ?? $text,
            url: $url,
            title: $firstLine,
        );
    }

    public static function configRules(): array
    {
        return [
            'config.chat_id' => ['required', 'string', 'max:32'],
        ];
    }
}
