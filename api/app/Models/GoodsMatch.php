<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsMatch extends Model
{
    /** Interest requests one member may send per day. */
    public const REQUESTS_PER_DAY = 10;

    /** Failed deals in a year that take a member off the board. */
    public const FAILURES_BEFORE_BLOCK = 2;

    /** Days after the check-in date the bot keeps asking before it settles the match. */
    public const CHECKIN_DAYS = 14;

    /** Statuses that still lead somewhere. */
    public const LIVE = ['requested', 'introduced', 'agreed', 'disputed'];

    protected $fillable = [
        'post_id', 'requester_post_id', 'owner_id', 'requester_id', 'status', 'kilos', 'introduced_at',
        'agreed_owner_at', 'agreed_requester_at', 'agreed_at', 'checkin_on', 'checkin_sent_at', 'checkin_owner',
        'checkin_requester', 'closed_at', 'failed_member_id', 'moderator_note', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'introduced_at' => 'datetime',
            'agreed_owner_at' => 'datetime',
            'agreed_requester_at' => 'datetime',
            'agreed_at' => 'datetime',
            'checkin_on' => 'date',
            'checkin_sent_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(GoodsPost::class, 'post_id');
    }

    public function requesterPost(): BelongsTo
    {
        return $this->belongsTo(GoodsPost::class, 'requester_post_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'owner_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requester_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class, 'match_id');
    }

    public function involves(Member $member): bool
    {
        return in_array($member->id, [$this->owner_id, $this->requester_id], true);
    }

    public function isOwner(Member $member): bool
    {
        return $member->id === $this->owner_id;
    }

    public function other(Member $member): ?Member
    {
        return $this->isOwner($member) ? $this->requester : $this->owner;
    }

    public function travellerPost(): ?GoodsPost
    {
        return $this->post?->isTraveller() ? $this->post : $this->requesterPost;
    }

    public function senderPost(): ?GoodsPost
    {
        return $this->post?->isTraveller() ? $this->requesterPost : $this->post;
    }

    public function traveller(): ?Member
    {
        return $this->travellerPost()?->member;
    }

    /** Matches a moderator decided against this member in the last year. */
    public static function failuresFor(Member $member): int
    {
        return static::query()->where('failed_member_id', $member->id)->where('status', 'failed')
            ->where('closed_at', '>=', now()->subYear())->count();
    }

    public static function blocked(Member $member): bool
    {
        return self::failuresFor($member) >= self::FAILURES_BEFORE_BLOCK;
    }
}
