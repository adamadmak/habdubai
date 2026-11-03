<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An update as Telegram delivered it, kept only as long as it is needed: the
 * full payload until it has been handled (a contact card in it carries the
 * member's phone number), then only its id and kind, and nothing at all after
 * KEEP_DAYS (`bot:updates-chores`).
 */
class TelegramUpdate extends Model
{
    public const KEEP_DAYS = 7;

    /** Tries before an update that keeps failing is left for an administrator to read. */
    public const MAX_ATTEMPTS = 3;

    public $timestamps = false;

    protected $fillable = ['update_id', 'payload', 'received_at', 'processed_at', 'error', 'attempts'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /** What is left of a handled update: its id and its kind, none of its content. */
    public static function redact(array $payload): array
    {
        return [
            'update_id' => (int) ($payload['update_id'] ?? 0),
            'kind' => (string) (array_values(array_diff(array_keys($payload), ['update_id']))[0] ?? ''),
            'redacted' => true,
        ];
    }

    /**
     * A person's own updates run one at a time. Button presses key on who pressed
     * (a channel card is pressed by many people at once).
     */
    public static function orderKey(array $payload): int
    {
        return (int) (data_get($payload, 'callback_query.from.id')
            ?? data_get($payload, 'message.chat.id')
            ?? data_get($payload, 'edited_message.chat.id')
            ?? data_get($payload, 'channel_post.chat.id')
            ?? 0);
    }
}
