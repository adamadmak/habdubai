<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberTrade extends Model
{
    public $timestamps = false;

    protected $fillable = ['member_id', 'trade'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
