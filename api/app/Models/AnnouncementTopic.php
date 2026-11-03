<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementTopic extends Model
{
    protected $fillable = ['key', 'name_en', 'name_am', 'name_ti', 'emoji', 'needs_approval', 'default_on', 'pin', 'sort', 'active'];

    protected function casts(): array
    {
        return ['needs_approval' => 'boolean', 'default_on' => 'boolean', 'pin' => 'boolean', 'active' => 'boolean'];
    }

    public function name(string $language): string
    {
        return (string) (match ($language) { 'am' => $this->name_am, 'ti' => $this->name_ti, default => $this->name_en } ?: $this->name_en);
    }

    public function label(string $language): string
    {
        return $this->emoji.' '.$this->name($language);
    }

    public static function listed()
    {
        return static::query()->where('active', true)->orderBy('sort')->get();
    }

    /** Topics this member may post in: every topic for moderators and admins, granted ones for leaders. */
    public static function postableBy(Member $member)
    {
        if ($member->hasRole('moderator', 'admin')) {
            return static::listed();
        }

        if (! $member->hasRole('leader')) {
            return collect();
        }

        $granted = \Illuminate\Support\Facades\DB::table('leader_topics')->where('member_id', $member->id)->pluck('topic_id');

        return static::listed()->whereIn('id', $granted)->values();
    }

    /** Is this member subscribed? Their explicit choice, or the topic's default. */
    public function isSubscribed(Member $member): bool
    {
        $pref = \Illuminate\Support\Facades\DB::table('member_topic_prefs')->where('member_id', $member->id)->where('topic_id', $this->id)->value('subscribed');

        return $pref === null ? $this->default_on : (bool) $pref;
    }

    /** Active members who should get this topic's announcements by DM. */
    public function subscribers()
    {
        $optedOut = \Illuminate\Support\Facades\DB::table('member_topic_prefs')->where('topic_id', $this->id)->where('subscribed', false)->pluck('member_id');
        $optedIn = \Illuminate\Support\Facades\DB::table('member_topic_prefs')->where('topic_id', $this->id)->where('subscribed', true)->pluck('member_id');

        $query = Member::query()->whereNotNull('agreed_rules_at')->whereNull('banned_at')->whereNull('left_at')->whereNotNull('chat_id');

        return $this->default_on ? $query->whereNotIn('id', $optedOut) : $query->whereIn('id', $optedIn);
    }
}
