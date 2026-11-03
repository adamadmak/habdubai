<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's 1–5 stars for the other side of a completed match. Hidden
 * until both sides rated or a week passed, so nobody rates in reply.
 */
class Rating extends Model
{
    public $timestamps = false;

    public const VISIBLE_AFTER_DAYS = 7;

    protected $fillable = ['match_id', 'from_member_id', 'to_member_id', 'stars', 'visible_at', 'counted_at', 'created_at'];

    protected function casts(): array
    {
        return ['visible_at' => 'datetime', 'counted_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'from_member_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'to_member_id');
    }

    /** Recompute a member's shown average from the ratings visible now. */
    public static function recount(int $memberId): void
    {
        $visible = static::query()->where('to_member_id', $memberId)->where('visible_at', '<=', now());
        $count = (clone $visible)->count();

        Member::query()->whereKey($memberId)->update([
            'rating_count' => $count,
            'rating_avg' => $count > 0 ? round((float) (clone $visible)->avg('stars'), 2) : null,
        ]);

        (clone $visible)->whereNull('counted_at')->update(['counted_at' => now()]);
    }

    /** Count every rating whose week has passed; returns how many members changed. */
    public static function revealDue(): int
    {
        $members = static::query()->where('visible_at', '<=', now())->whereNull('counted_at')->distinct()->pluck('to_member_id');

        foreach ($members as $id) {
            self::recount((int) $id);
        }

        return $members->count();
    }
}
