<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobSource extends Model
{
    public const KINDS = ['rss', 'html', 'telegram'];

    /** A source that fails this many runs in a row is paused and an admin is told. */
    public const MAX_FAILURES = 3;

    protected $fillable = ['key', 'name', 'kind', 'config', 'interval_minutes', 'active', 'paused_at', 'pause_reason'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'active' => 'boolean',
            'paused_at' => 'datetime',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function raws(): HasMany
    {
        return $this->hasMany(JobRaw::class, 'source_id');
    }

    public function isRunnable(): bool
    {
        return $this->active && $this->paused_at === null && $this->kind !== 'telegram';
    }

    public function isDue(): bool
    {
        return $this->isRunnable()
            && ($this->last_run_at === null || $this->last_run_at->addMinutes(max(5, $this->interval_minutes))->isPast());
    }

    public function cfg(string $key, mixed $default = null): mixed
    {
        return ($this->config ?? [])[$key] ?? $default;
    }

    /** The source whose config names this Telegram chat, if it is an active telegram source. */
    public static function forTelegramChat(int|string $chatId): ?self
    {
        return static::query()
            ->where('kind', 'telegram')
            ->where('active', true)
            ->whereNull('paused_at')
            ->get()
            ->first(fn (self $s) => (string) $s->cfg('chat_id') === (string) $chatId);
    }

    /** The built-in source for positions a business posts on the website. Never fetched: `telegram` kind sources are not run. */
    public static function businessOwners(): self
    {
        return static::query()->firstOrCreate(
            ['key' => 'business-owners'],
            ['name' => 'Posted by a business', 'kind' => 'telegram', 'config' => ['chat_id' => null], 'interval_minutes' => 60, 'active' => true],
        );
    }

    /** The built-in source for what admins post by hand on our own job channel. */
    public static function ownChannel(): self
    {
        return static::query()->firstOrCreate(
            ['key' => 'own-channel'],
            ['name' => 'HabUAE channel', 'kind' => 'telegram', 'config' => ['chat_id' => null], 'interval_minutes' => 0, 'active' => true],
        );
    }

    /** The built-in source for job posts a moderator forwards to the bot. */
    public static function forwarded(): self
    {
        return static::query()->firstOrCreate(
            ['key' => 'forwarded'],
            ['name' => 'Forwarded by moderators', 'kind' => 'telegram', 'config' => ['chat_id' => null], 'interval_minutes' => 0, 'active' => true]
        );
    }
}
