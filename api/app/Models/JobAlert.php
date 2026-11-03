<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAlert extends Model
{
    public $timestamps = false;

    protected $fillable = ['member_id', 'job_id', 'created_at', 'sent_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
