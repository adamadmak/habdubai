<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantMessage extends Model
{
    public $timestamps = false;

    /** Turns kept per member; older ones are deleted as new ones arrive. */
    public const KEEP = 10;

    protected $fillable = ['member_id', 'role', 'text', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function remember(int $memberId, string $role, string $text): void
    {
        static::query()->create(['member_id' => $memberId, 'role' => $role, 'text' => mb_substr($text, 0, 4000), 'created_at' => now()]);

        $keep = static::query()->where('member_id', $memberId)->latest('id')->limit(self::KEEP)->pluck('id');
        static::query()->where('member_id', $memberId)->whereNotIn('id', $keep)->delete();
    }
}
