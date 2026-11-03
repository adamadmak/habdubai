<?php

namespace App\Bot;

/** A pressed inline button: who pressed it, where the message is, and what it carries. */
final class Callback
{
    public function __construct(
        public readonly string $id,
        public readonly int $userId,
        public readonly string $data,
        public readonly ?int $chatId,
        public readonly ?string $chatType,
        public readonly ?int $messageId,
        public readonly ?string $languageCode,
    ) {}

    public static function fromUpdate(array $update): ?self
    {
        $query = $update['callback_query'] ?? null;

        if (! is_array($query) || ! isset($query['id'], $query['from']['id'])) {
            return null;
        }

        return new self(
            id: (string) $query['id'],
            userId: (int) $query['from']['id'],
            data: (string) ($query['data'] ?? ''),
            chatId: isset($query['message']['chat']['id']) ? (int) $query['message']['chat']['id'] : null,
            chatType: $query['message']['chat']['type'] ?? null,
            messageId: isset($query['message']['message_id']) ? (int) $query['message']['message_id'] : null,
            languageCode: $query['from']['language_code'] ?? null,
        );
    }

    /** "jf:cat:driver" → ['jf', 'cat', 'driver'] */
    public function parts(): array
    {
        return explode(':', $this->data);
    }

    public function inPrivateChat(): bool
    {
        return $this->chatType === 'private';
    }
}
