<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An owner saying "this listing is mine". A moderator checks before handing it over. */
class BusinessClaim extends Model
{
    protected $fillable = ['business_id', 'owner_id', 'note', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class, 'owner_id');
    }
}
