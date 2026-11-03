<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupReport extends Model
{
    /** Distinct reports that take a message down before a moderator looks. */
    public const HIDE_THRESHOLD = 3;

    protected $fillable = ['reporter_id', 'chat_id', 'message_id', 'reported_member_id', 'excerpt', 'status', 'action', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reporter_id');
    }

    public function reported(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reported_member_id');
    }
}
