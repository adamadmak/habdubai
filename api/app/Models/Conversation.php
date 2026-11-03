<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The chat's place in a multi-step flow. `state` names the step, `data`
 * holds what earlier steps collected. 'idle' means no flow is running.
 */
class Conversation extends Model
{
    protected $fillable = ['telegram_user_id', 'chat_id', 'language', 'state', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /**
     * Until the person picks a language, Telegram's own hint decides: an
     * Amharic or Tigrinya client gets that, anything else gets English.
     */
    public static function forUser(int $telegramUserId, int $chatId, ?string $languageHint = null): self
    {
        $hint = in_array($languageHint, ['am', 'ti'], true) ? $languageHint : 'en';

        $conversation = static::query()->firstOrCreate(
            ['telegram_user_id' => $telegramUserId],
            ['chat_id' => $chatId, 'language' => $hint, 'state' => 'idle', 'data' => []]
        );

        if ((int) $conversation->chat_id !== $chatId) {
            $conversation->forceFill(['chat_id' => $chatId])->save();
        }

        return $conversation;
    }

    public function moveTo(string $state, array $data = []): void
    {
        $this->forceFill(['state' => $state, 'data' => $data + ($this->data ?? [])])->save();
    }

    public function reset(): void
    {
        $this->forceFill(['state' => 'idle', 'data' => []])->save();
    }

    public function remember(string $key, mixed $value): void
    {
        $data = $this->data ?? [];
        $data[$key] = $value;
        $this->forceFill(['data' => $data])->save();
    }

    public function recall(string $key, mixed $default = null): mixed
    {
        return ($this->data ?? [])[$key] ?? $default;
    }
}
