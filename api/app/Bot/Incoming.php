<?php

namespace App\Bot;

/**
 * The parts of a Telegram update the handlers care about, pulled out once.
 * Private messages only for now: a group message has no handler yet and is
 * ignored by the router.
 */
final class Incoming
{
    public function __construct(
        public readonly int $chatId,
        public readonly string $chatType,
        public readonly int $userId,
        public readonly string $firstName,
        public readonly ?string $username,
        public readonly ?string $languageCode,
        public readonly ?string $text,
        public readonly ?array $contact,
        public readonly int $messageId,
    ) {}

    public static function fromUpdate(array $update): ?self
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if (! is_array($message) || ! isset($message['chat']['id'], $message['from']['id'])) {
            return null;
        }

        if (! empty($message['from']['is_bot'])) {
            return null;
        }

        return new self(
            chatId: (int) $message['chat']['id'],
            chatType: (string) ($message['chat']['type'] ?? 'private'),
            userId: (int) $message['from']['id'],
            firstName: trim((string) ($message['from']['first_name'] ?? '')),
            username: isset($message['from']['username']) ? (string) $message['from']['username'] : null,
            languageCode: isset($message['from']['language_code']) ? (string) $message['from']['language_code'] : null,
            text: isset($message['text']) ? trim((string) $message['text']) : null,
            contact: isset($message['contact']) && is_array($message['contact']) ? $message['contact'] : null,
            messageId: (int) ($message['message_id'] ?? 0),
        );
    }

    public function isPrivate(): bool
    {
        return $this->chatType === 'private';
    }

    /** "/start" and "/start something" both answer 'start'; anything else is null. */
    public function command(): ?string
    {
        if ($this->text === null || ! str_starts_with($this->text, '/')) {
            return null;
        }

        $word = strtolower(strtok(substr($this->text, 1), " \n@") ?: '');

        return $word !== '' ? $word : null;
    }

    public function commandArgument(): string
    {
        if ($this->command() === null) {
            return '';
        }

        $parts = preg_split('/\s+/', $this->text, 2) ?: [];

        return trim($parts[1] ?? '');
    }
}
