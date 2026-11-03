<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    public const ROLES = ['member', 'leader', 'moderator', 'admin'];

    protected $fillable = [
        'telegram_user_id', 'chat_id', 'telegram_username', 'phone_hash', 'phone_last4', 'phone_country',
        'needs_review', 'display_name', 'emirate', 'language', 'role', 'verified_at', 'agreed_rules_at',
        'left_at', 'banned_at', 'ban_reason',
    ];

    protected function casts(): array
    {
        return [
            'needs_review' => 'boolean',
            'verified_at' => 'datetime',
            'agreed_rules_at' => 'datetime',
            'left_at' => 'datetime',
            'banned_at' => 'datetime',
            'muted_until' => 'datetime',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function trades(): HasMany
    {
        return $this->hasMany(MemberTrade::class)->orderBy('id');
    }

    /** @return array<int, string> */
    public function tradeKeys(): array
    {
        return $this->trades->pluck('trade')->all();
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    /** Leaders, moderators and admins are trusted: the automatic checks leave them alone. */
    public function isTrusted(): bool
    {
        return $this->hasRole('leader', 'moderator', 'admin');
    }

    public function hasLeft(): bool
    {
        return $this->left_at !== null;
    }

    /** Verified, agreed to the rules, not banned, not left: may use the groups. */
    public function isActive(): bool
    {
        return $this->agreed_rules_at !== null && ! $this->isBanned() && ! $this->hasLeft();
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public static function findByTelegramUser(int $telegramUserId): ?self
    {
        return static::query()->where('telegram_user_id', $telegramUserId)->first();
    }

    /** What the bot shows other members: name, verified month, and reputation. */
    public function badge(): string
    {
        $since = $this->verified_at?->format('M Y') ?? '';

        return trim($this->display_name.' ✅ '.$since);
    }
}
