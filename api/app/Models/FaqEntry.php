<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqEntry extends Model
{
    protected $fillable = ['question_id', 'category', 'q_am', 'q_ti', 'q_en', 'a_am', 'a_ti', 'a_en', 'source_url', 'active', 'promoted_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function q(string $language): string
    {
        return (string) (match ($language) { 'am' => $this->q_am, 'ti' => $this->q_ti, default => null } ?: $this->q_en);
    }

    public function a(string $language): string
    {
        return (string) (match ($language) { 'am' => $this->a_am, 'ti' => $this->a_ti, default => null } ?: $this->a_en);
    }
}
