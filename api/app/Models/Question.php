<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    public const QUIET_DAYS = 7;

    protected $fillable = [
        'member_id', 'anonymous', 'category', 'title', 'body', 'group_chat_id', 'group_message_id', 'forum_topic_id',
        'status', 'solved_reply_id', 'faq_id', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return ['anonymous' => 'boolean', 'last_activity_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(QuestionReply::class)->orderBy('id');
    }

    public function solvedReply(): BelongsTo
    {
        return $this->belongsTo(QuestionReply::class, 'solved_reply_id');
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(FaqEntry::class, 'faq_id');
    }

    /** Who may mark it solved: the asker, or a moderator or admin. */
    public function canBeSolvedBy(Member $member): bool
    {
        return $member->id === $this->member_id || $member->hasRole('moderator', 'admin');
    }

    /** "Who is asking" as the group sees it. */
    public function askerName(): string
    {
        return $this->anonymous ? '' : (string) $this->member?->display_name;
    }
}
