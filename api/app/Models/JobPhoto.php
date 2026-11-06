<?php

namespace App\Models;

use App\Support\PublicUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A picture of a job, usually the flyer itself, re-encoded and kept in the database. */
class JobPhoto extends Model
{
    protected $fillable = ['job_id', 'position', 'mime', 'width', 'height', 'size', 'data'];

    protected $hidden = ['data'];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function bytes(): string
    {
        return (string) base64_decode((string) $this->data, true);
    }

    /** The version in the address lets the browser keep it for good. */
    public function url(?int $width = null): string
    {
        return PublicUrls::api('api/public/job-photos/'.$this->id.'?v='.($this->updated_at?->timestamp ?? 0).($width ? '&w='.$width : ''));
    }
}
