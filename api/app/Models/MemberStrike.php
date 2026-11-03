<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberStrike extends Model
{
    public $timestamps = false;

    protected $fillable = ['member_id', 'kind', 'reason', 'chat_id', 'until', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['until' => 'datetime', 'created_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public static function record(Member $member, string $kind, string $reason, ?int $chatId = null, ?\DateTimeInterface $until = null, ?int $actorId = null): self
    {
        return static::query()->create([
            'member_id' => $member->id, 'kind' => $kind, 'reason' => mb_substr($reason, 0, 250), 'chat_id' => $chatId,
            'until' => $until, 'actor_id' => $actorId, 'created_at' => now(),
        ]);
    }
}
