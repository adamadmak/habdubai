<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerLoginRequest extends Model
{
    public const LIFETIME_MINUTES = 10;

    protected $fillable = ['ref', 'secret_hash', 'check_code', 'status', 'telegram_user_id', 'owner_id', 'ip', 'user_agent', 'expires_at', 'approved_at', 'confirm_code_hash', 'attempts'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class, 'owner_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
