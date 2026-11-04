<?php

namespace App\Models;

use App\Support\TrustScore;
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

    /** The fields an owner may set or change; a change to a live listing waits for review. */
    public const OWNER_FIELDS = [
        'city_id', 'category', 'name', 'area', 'latitude', 'longitude', 'maps_url', 'phone', 'whatsapp', 'telegram',
        'website', 'description', 'about', 'hours', 'languages',
    ];

    protected $fillable = [
        'city_id', 'category', 'name', 'area', 'latitude', 'longitude', 'maps_url', 'phone', 'telegram',
        'description', 'status', 'verified', 'submitted_by', 'reviewed_by', 'reviewed_at', 'review_note', 'reports_count',
        'owner_id', 'slug', 'about', 'website', 'whatsapp', 'hours', 'languages', 'pending_changes', 'changes_submitted_at',
        'submitted_at', 'channel_message_id', 'views',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'reviewed_at' => 'datetime',
            'languages' => 'array',
            'pending_changes' => 'array',
            'changes_submitted_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A stable address for the website: the name in Latin letters plus the id, set once.
        static::created(function (self $business): void {
            if ($business->slug === null) {
                $business->forceFill(['slug' => self::slugFor($business->name, $business->id)])->saveQuietly();
            }
        });
    }

    public static function slugFor(string $name, int $id): string
    {
        $base = \Illuminate\Support\Str::of($name)->ascii()->slug()->limit(80, '')->trim('-')->toString();

        return ($base !== '' ? $base.'-' : 'business-').$id;
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(DirectoryCity::class, 'city_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'submitted_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class, 'owner_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BusinessPhoto::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(BusinessClaim::class);
    }

    /** The live cover photo, or the newest one still in review when $withPending. */
    public function cover(bool $withPending = false): ?BusinessPhoto
    {
        $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get(['id', 'business_id', 'kind', 'approved', 'updated_at', 'created_at']);

        $cover = $photos->where('kind', 'cover');

        return ($withPending ? $cover->sortByDesc('id')->first() : null)
            ?? $cover->where('approved', true)->sortByDesc('id')->first();
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(BusinessRating::class);
    }

    /** Whether this member owns the listing, by the account that claimed it. */
    public function isOwnedBy(Member $member): bool
    {
        if ($this->owner_id === null) {
            return false;
        }

        return BusinessOwner::query()->whereKey($this->owner_id)
            ->where(fn ($q) => $q->where('member_id', $member->id)->orWhere('telegram_user_id', $member->telegram_user_id))
            ->exists();
    }

    /** Stars out of five, or null before anyone has rated it, and how many ratings that is. */
    public function ratingPayload(): array
    {
        return [
            'rating' => (int) $this->rating_count > 0 ? round((float) $this->rating_avg, 1) : null,
            'ratings' => (int) $this->rating_count,
            'top' => TrustScore::isTop($this->rating_avg, $this->rating_count),
        ];
    }

    /** What the website shows. Only ever called for a listed business. */
    public function publicPayload(bool $full = false): array
    {
        $city = $this->city;
        $cover = $this->cover();

        $payload = [
            'id' => $this->id,
            'slug' => $this->slug ?? self::slugFor($this->name, $this->id),
            'name' => $this->name,
            'category' => $this->category,
            'city' => $city ? ['key' => $city->key, 'name' => ['en' => $city->name_en, 'am' => $city->name_am, 'ti' => $city->name_ti, 'om' => $city->name_om]] : null,
            'area' => $this->area,
            'description' => $this->description,
            'verified' => $this->verified,
            'owner_managed' => $this->owner_id !== null,
            'photo' => $cover?->url(),
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'telegram' => $this->telegram,
        ] + $this->ratingPayload();

        if ($full) {
            $payload += [
                // How many gave each number of stars, from five down.
                'rating_breakdown' => (function (): array {
                    $counts = $this->ratings()->counting()->selectRaw('stars, count(*) as n')->groupBy('stars')->pluck('n', 'stars');

                    return array_map(fn (int $stars) => ['stars' => $stars, 'count' => (int) ($counts[$stars] ?? 0)], range(BusinessRating::MAX_STARS, BusinessRating::MIN_STARS));
                })(),
                'about' => $this->about,
                'website' => $this->website,
                'hours' => $this->hours,
                'languages' => $this->languages ?? [],
                'map_link' => $this->mapLink(),
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'updated_at' => $this->updated_at?->toIso8601String(),
            ];
        }

        return $payload;
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

    /** Best standing first, then the better known of two equals. The caller adds what settles a tie. */
    public function scopeBestRatedFirst($query)
    {
        return $query->orderByRaw(TrustScore::sql('rating_avg', 'rating_count').' desc')->orderByDesc('rating_count');
    }
}
