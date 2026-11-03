<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    /** A poster may edit their announcement for this long after publishing. */
    public const EDIT_MINUTES = 60;

    public const PIN_HOURS = 48;

    protected $fillable = [
        'topic_id', 'member_id', 'user_id', 'original_language', 'text_am', 'text_ti', 'text_en', 'text_om', 'photo_file_id',
        'event_date', 'event_place', 'status', 'review_note', 'reviewed_by', 'channel_message_id', 'published_at',
        'pinned_until', 'reminder_sent_at', 'dm_count',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'published_at' => 'datetime',
            'pinned_until' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(AnnouncementTopic::class, 'topic_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function text(string $language): string
    {
        return (string) (match ($language) { 'am' => $this->text_am, 'ti' => $this->text_ti, 'om' => $this->text_om, default => $this->text_en } ?: $this->text_en);
    }

    public function posterName(): string
    {
        return $this->member?->display_name ?? 'Habesha Dubai';
    }
}
