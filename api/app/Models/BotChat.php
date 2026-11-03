<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotChat extends Model
{
    protected $fillable = ['chat_id', 'type', 'title', 'username', 'is_forum', 'bot_status'];

    protected function casts(): array
    {
        return ['is_forum' => 'boolean'];
    }

    /** Record what Telegram tells us when the bot is added, promoted or removed. */
    public static function fromMembershipUpdate(array $update): void
    {
        $chat = $update['my_chat_member']['chat'] ?? null;
        $status = $update['my_chat_member']['new_chat_member']['status'] ?? null;

        if (! is_array($chat) || ! isset($chat['id']) || ($chat['type'] ?? '') === 'private' || $status === null) {
            return;
        }

        static::query()->updateOrCreate(['chat_id' => (int) $chat['id']], [
            'type' => (string) $chat['type'],
            'title' => isset($chat['title']) ? mb_substr((string) $chat['title'], 0, 160) : null,
            'username' => $chat['username'] ?? null,
            'is_forum' => (bool) ($chat['is_forum'] ?? false),
            'bot_status' => (string) $status,
        ]);
    }

    /** A later message tells us the title or forum flag changed. */
    public static function touchFromMessage(array $chat): void
    {
        if (! isset($chat['id']) || ($chat['type'] ?? 'private') === 'private') {
            return;
        }

        static::query()->where('chat_id', (int) $chat['id'])->update(array_filter([
            'title' => isset($chat['title']) ? mb_substr((string) $chat['title'], 0, 160) : null,
            'username' => $chat['username'] ?? null,
            'is_forum' => isset($chat['is_forum']) ? (bool) $chat['is_forum'] : null,
            'updated_at' => now(),
        ], fn ($v) => $v !== null));
    }
}
