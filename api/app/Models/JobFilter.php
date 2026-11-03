<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobFilter extends Model
{
    protected $fillable = ['member_id', 'categories', 'emirates', 'salary_min', 'keyword', 'alerts_on', 'last_alert_at'];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'emirates' => 'array',
            'alerts_on' => 'boolean',
            'last_alert_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public static function forMember(Member $member): self
    {
        return static::query()->firstOrCreate(
            ['member_id' => $member->id],
            ['categories' => $member->tradeKeys(), 'emirates' => [], 'alerts_on' => true]
        );
    }

    /**
     * Does this job match? Empty category or emirate lists mean "all". A
     * minimum salary rules out a job whose stated salary is lower, but not a
     * job that states none: most listings do not, and excluding them would
     * leave the member with almost nothing.
     */
    public function matches(Job $job): bool
    {
        $categories = $this->categories ?? [];
        if ($categories !== [] && ! in_array($job->category, $categories, true)) {
            return false;
        }

        $emirates = $this->emirates ?? [];
        if ($emirates !== [] && ! in_array($job->emirate, $emirates, true)) {
            return false;
        }

        if ($this->salary_min) {
            $stated = $job->salary_max ?: $job->salary_min;
            if ($stated && $stated < $this->salary_min) {
                return false;
            }
        }

        if ($this->keyword) {
            $haystack = mb_strtolower(implode(' ', [$job->title, $job->company, $job->summary_en, $job->sub_area]));
            if (! str_contains($haystack, mb_strtolower($this->keyword))) {
                return false;
            }
        }

        return true;
    }
}
