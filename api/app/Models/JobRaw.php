<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobRaw extends Model
{
    public $timestamps = false;

    protected $table = 'job_raw';

    protected $fillable = ['source_id', 'external_key', 'url', 'title', 'company', 'location', 'raw_text', 'business_id', 'posted_by', 'posted_at', 'fetched_at', 'processed_at'];

    protected function casts(): array
    {
        return [
            'posted_by' => 'array',
            'posted_at' => 'datetime',
            'fetched_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'source_id');
    }
}
