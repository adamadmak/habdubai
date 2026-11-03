<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'hidden'];

    /** Distinct member reports that take a listing down until a moderator looks. */
    public const HIDE_THRESHOLD = 3;

    /** How many suggestions one member may have waiting at once. */
    public const MAX_PENDING_PER_MEMBER = 5;

    protected $fillable = [
        'city_id', 'category', 'name', 'area', 'latitude', 'longitude', 'maps_url', 'phone', 'telegram',
        'description', 'status', 'verified', 'submitted_by', 'reviewed_by', 'reviewed_at', 'review_note', 'reports_count',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(DirectoryCity::class, 'city_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'submitted_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(BusinessReport::class);
    }

    public function hasPin(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** A link that opens the place in the phone's maps app: the pin, the shared link, or a search. */
    public function mapLink(): string
    {
        if ($this->hasPin()) {
            return 'https://maps.google.com/?q='.$this->latitude.','.$this->longitude;
        }

        if ($this->maps_url) {
            return $this->maps_url;
        }

        return 'https://maps.google.com/?q='.rawurlencode($this->name.', '.$this->area.', '.($this->city?->name_en ?? ''));
    }

    public function scopeListed($query)
    {
        return $query->where('status', 'approved');
    }
}
