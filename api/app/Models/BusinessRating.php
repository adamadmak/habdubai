<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One member's 1–5 stars for one business. They may change them; a banned member's stars
 * stop counting. recount() is the only writer of a business's rating columns.
 */
class BusinessRating extends Model
{
    public const MIN_STARS = 1;

    public const MAX_STARS = 5;

    protected $fillable = ['business_id', 'member_id', 'stars'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function scopeCounting($query)
    {
        return $query->whereHas('member', fn ($member) => $member->whereNull('banned_at'));
    }

    public static function recount(int $businessId): void
    {
        // One at a time per place: two members rating together must not leave the older count on it.
        DB::transaction(function () use ($businessId): void {
            if (DB::table('businesses')->where('id', $businessId)->lockForUpdate()->value('id') === null) {
                return;
            }

            $counting = static::query()->where('business_id', $businessId)->counting();
            $count = (clone $counting)->count();

            // Without touching updated_at, which the page shows as "last updated".
            DB::table('businesses')->where('id', $businessId)->update([
                'rating_count' => $count,
                'rating_avg' => $count > 0 ? round((float) (clone $counting)->avg('stars'), 2) : null,
            ]);
        });
    }

    /** Stars someone gave a place before they came to own it stop counting. */
    public static function forgetOwners(Business $business): void
    {
        $member = $business->owner?->communityMember();

        if ($member !== null && static::query()->where('business_id', $business->id)->where('member_id', $member->id)->delete() > 0) {
            self::recount($business->id);
        }
    }

    /** A member banned or let back in: every business they rated is counted again. */
    public static function recountFor(Member $member): void
    {
        foreach (static::query()->where('member_id', $member->id)->pluck('business_id') as $businessId) {
            self::recount((int) $businessId);
        }
    }

    /**
     * A member's stars for a listed business, from the bot or the website.
     *
     * @return string saved|own|closed|not_member
     */
    public static function give(Member $member, Business $business, int $stars): string
    {
        if (! $member->isActive()) {
            return 'not_member';
        }
        if ($business->status !== 'approved') {
            return 'closed';
        }
        if ($business->isOwnedBy($member)) {
            return 'own';
        }

        static::query()->updateOrCreate(['business_id' => $business->id, 'member_id' => $member->id], ['stars' => $stars]);
        self::recount($business->id);
        $business->refresh();

        return 'saved';
    }
}
