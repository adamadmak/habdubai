<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobReport extends Model
{
    public $timestamps = false;

    /** Distinct member reports that take a card off the channel until a moderator decides. */
    public const HIDE_THRESHOLD = 3;

    protected $fillable = ['member_id', 'job_id', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
