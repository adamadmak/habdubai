<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Someone who manages listings on the website. They sign in with Telegram
 * (no password, no email) and need not be a community member; when they
 * are, the account is linked to the member. Their tokens carry only the
 * "owner" ability, so they can never reach an admin route.
 */
class BusinessOwner extends Authenticatable
{
    use HasApiTokens;

    /** Listings one owner may have in total, and waiting for review at once. */
    public const MAX_LISTINGS = 10;

    public const MAX_PENDING = 3;

    protected $fillable = ['telegram_user_id', 'member_id', 'display_name', 'telegram_username', 'language', 'last_login_at', 'banned_at'];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return ['last_login_at' => 'datetime', 'banned_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(BusinessClaim::class, 'owner_id');
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null || $this->member?->isBanned();
    }

    /** Private chats with the bot have the user's id as the chat id. */
    public function chatId(): int
    {
        return (int) $this->telegram_user_id;
    }

    public function payload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->display_name,
            'username' => $this->telegram_username,
            'language' => $this->language,
            'member' => $this->member_id !== null,
        ];
    }
}
