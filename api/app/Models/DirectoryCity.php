<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DirectoryCity extends Model
{
    protected $fillable = ['key', 'name_en', 'name_am', 'name_ti', 'country', 'sort', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'city_id');
    }

    public function name(string $language): string
    {
        return match ($language) {
            'am' => $this->name_am,
            'ti' => $this->name_ti,
            default => $this->name_en,
        } ?: $this->name_en;
    }

    /** Active cities in display order. */
    public static function listed()
    {
        return static::query()->where('active', true)->orderBy('sort')->orderBy('name_en')->get();
    }

    /** The city whose name in any language is exactly this text (a pressed button). */
    public static function fromLabel(string $text): ?self
    {
        $text = trim($text);

        return static::listed()->first(fn (self $c) => in_array($text, [$c->name_en, $c->name_am, $c->name_ti], true));
    }
}
