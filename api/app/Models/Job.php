<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One job listing as members see it. The table is `jobs_listings` because
 * Laravel's queue already owns `jobs`.
 */
class Job extends Model
{
    protected $table = 'jobs_listings';

    public const STATUSES = ['pending', 'review', 'published', 'rejected', 'duplicate', 'closed'];

    public const EMIRATES = ['dubai', 'sharjah', 'abu_dhabi', 'ajman', 'other_uae', 'outside_uae', 'unknown'];

    /** Days a published job stays open before it is closed automatically. */
    public const OPEN_DAYS = 14;

    protected $fillable = [
        'raw_id', 'source_id', 'duplicate_of', 'url', 'url_key', 'fingerprint', 'category', 'emirate', 'sub_area',
        'title', 'company', 'salary_min', 'salary_max', 'salary_currency', 'accommodation', 'visa', 'languages',
        'summary_am', 'summary_ti', 'summary_en', 'summary_om', 'scam_score', 'scam_reasons', 'classified_by', 'status',
        'review_reason', 'reviewed_by', 'reviewed_at', 'published_at', 'closed_at', 'channel_message_id', 'reports_count',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'scam_reasons' => 'array',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function raw(): BelongsTo
    {
        return $this->belongsTo(JobRaw::class, 'raw_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'source_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(JobReport::class, 'job_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'published';
    }

    public function summary(string $language): string
    {
        $summary = match ($language) {
            'am' => $this->summary_am,
            'ti' => $this->summary_ti,
            'om' => $this->summary_om,
            default => $this->summary_en,
        };

        return trim((string) ($summary ?: $this->summary_en));
    }

    /** "AED 2,500 – 3,000" or null when nothing was stated. */
    public function salaryText(): ?string
    {
        if (! $this->salary_min && ! $this->salary_max) {
            return null;
        }

        $currency = $this->salary_currency ?: 'AED';
        $min = $this->salary_min ? number_format($this->salary_min) : null;
        $max = $this->salary_max ? number_format($this->salary_max) : null;

        return $currency.' '.($min && $max && $min !== $max ? $min.' – '.$max : ($min ?? $max));
    }
}
