<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_log';

    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'created_at'];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Every moderator and admin action, with what it changed. */
    public static function record(?int $actorId, string $action, ?string $subjectType = null, ?int $subjectId = null, ?array $before = null, ?array $after = null): self
    {
        return static::query()->create([
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $after,
            'created_at' => now(),
        ]);
    }
}
