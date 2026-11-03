<?php

namespace App\Models;

use App\Support\PublicUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** A listing's cover photo, re-encoded on upload and kept in the database. */
class BusinessPhoto extends Model
{
    protected $fillable = ['business_id', 'kind', 'approved', 'mime', 'width', 'height', 'size', 'data'];

    protected $hidden = ['data'];

    protected function casts(): array
    {
        return ['approved' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function bytes(): string
    {
        return (string) base64_decode((string) $this->data, true);
    }

    /**
     * Approved photos are public; one still in review is only reachable
     * through a relative signed link (relative, so the proxy in front of the
     * API cannot break the signature).
     */
    public function url(): string
    {
        if ($this->approved) {
            return PublicUrls::api('api/public/photos/'.$this->id.'?v='.($this->updated_at?->timestamp ?? 0));
        }

        return PublicUrls::api(URL::temporarySignedRoute('public.photo.preview', now()->addHours(12), ['photo' => $this->id], absolute: false));
    }
}
