<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A traveller's trip with spare kilos, or a member's parcel that needs a
 * traveller. A trip closes after its flight date; a parcel's date is
 * approximate, so it stays open until three days after it.
 */
class GoodsPost extends Model
{
    public const KINDS = ['traveller', 'sender'];

    public const DIRECTIONS = ['to_et', 'to_ae'];

    /** A parcel its owner sends, or one handed over at the other end for the member who asked. */
    public const MODES = ['send', 'request'];

    /** Currencies a carry fee may be stated in; members pay each other directly. */
    public const CURRENCIES = ['AED', 'ETB'];

    /** Open posts of each kind one member may have at once. */
    public const MAX_OPEN = 3;

    /** How far apart a flight and a sending date may be and still match. */
    public const MATCH_DAYS = 3;

    /** How far ahead a post may be dated. */
    public const MAX_DAYS_AHEAD = 120;

    protected $fillable = [
        'member_id', 'kind', 'mode', 'direction', 'date', 'city_from', 'city_to', 'kilos', 'kilos_left',
        'categories', 'description', 'price', 'currency', 'status', 'group_message_id', 'hidden_reason',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'categories' => 'array'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** Requests made about this post (it is the one asked about). */
    public function matches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GoodsMatch::class, 'post_id');
    }

    public function isTraveller(): bool
    {
        return $this->kind === 'traveller';
    }

    public static function today(): string
    {
        return now('Asia/Dubai')->toDateString();
    }

    /** The last day the post can still be matched. */
    public function lastDay(): CarbonInterface
    {
        return $this->isTraveller() ? $this->date->copy() : $this->date->copy()->addDays(self::MATCH_DAYS);
    }

    /** Open and not yet past its last day: can still be matched. */
    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->date !== null && $this->lastDay()->toDateString() >= self::today();
    }

    /** Kilos a traveller still has free. */
    public function room(): int
    {
        return (int) ($this->kilos_left ?? $this->kilos);
    }

    /** True when this post and that one can be paired: other kind, same way, close dates, the parcel fits. */
    public function pairsWith(self $other): bool
    {
        if ($other->kind === $this->kind || $other->direction !== $this->direction || $other->member_id === $this->member_id) {
            return false;
        }

        if (abs($this->date->diffInDays($other->date, false)) > self::MATCH_DAYS) {
            return false;
        }

        [$traveller, $sender] = $this->isTraveller() ? [$this, $other] : [$other, $this];

        return $traveller->room() >= $sender->kilos;
    }

    /**
     * Open posts that pair with this one, closest date first.
     *
     * @return Collection<int, self>
     */
    public function candidates(int $limit = 5, ?int $onlyMemberId = null): Collection
    {
        return static::query()->with('member')
            ->where('kind', $this->isTraveller() ? 'sender' : 'traveller')
            ->where('direction', $this->direction)
            ->where('status', 'open')
            ->where('member_id', '!=', $this->member_id)
            ->when($onlyMemberId !== null, fn (Builder $q) => $q->where('member_id', $onlyMemberId))
            ->whereDate('date', '>=', $this->date->copy()->subDays(self::MATCH_DAYS)->toDateString())
            ->whereDate('date', '<=', $this->date->copy()->addDays(self::MATCH_DAYS)->toDateString())
            ->get()
            ->filter(fn (self $p) => $p->isOpen() && $p->member?->isActive() && $this->pairsWith($p))
            ->sortBy(fn (self $p) => [abs($p->date->diffInDays($this->date, false)), $p->id])
            ->take($limit)
            ->values();
    }

    /** Posts that are open and not past their last day. */
    public function scopeLive(Builder $query): Builder
    {
        $today = self::today();
        $senderFrom = now('Asia/Dubai')->subDays(self::MATCH_DAYS)->toDateString();

        return $query->where('status', 'open')->where(function (Builder $q) use ($today, $senderFrom): void {
            $q->where(fn (Builder $t) => $t->where('kind', 'traveller')->whereDate('date', '>=', $today))
                ->orWhere(fn (Builder $s) => $s->where('kind', 'sender')->whereDate('date', '>=', $senderFrom));
        });
    }

    /** Open posts that have passed their last day. */
    public function scopeStale(Builder $query): Builder
    {
        $today = self::today();
        $senderBefore = now('Asia/Dubai')->subDays(self::MATCH_DAYS)->toDateString();

        return $query->where('status', 'open')->where(function (Builder $q) use ($today, $senderBefore): void {
            $q->where(fn (Builder $t) => $t->where('kind', 'traveller')->whereDate('date', '<', $today))
                ->orWhere(fn (Builder $s) => $s->where('kind', 'sender')->whereDate('date', '<', $senderBefore));
        });
    }

    /** @return Builder<self> */
    public static function openOf(Member $member, ?string $kind = null): Builder
    {
        return static::query()->live()->where('member_id', $member->id)
            ->when($kind !== null, fn (Builder $q) => $q->where('kind', $kind));
    }
}
