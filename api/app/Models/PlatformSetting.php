<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Operator-set values, edited on the admin's Settings page. Secrets live here
 * too (encrypted), because this host builds Laravel's config cache without
 * the runtime environment, so .env never reaches the application.
 */
class PlatformSetting extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** @var array<string, mixed> */
    protected static array $valueCache = [];

    protected static bool $allLoaded = false;

    /** The table is tiny: the first read pulls the lot, later reads are served from memory. */
    public static function valueFor(string $key, mixed $default = null): mixed
    {
        if (! static::$allLoaded && ! array_key_exists($key, static::$valueCache)) {
            static::warm();
        }

        $value = static::$valueCache[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        static::flushValueCache();
    }

    public static function warm(): void
    {
        try {
            static::$valueCache = static::query()->pluck('value', 'key')->all() + static::$valueCache;
            static::$allLoaded = true;
        } catch (\Throwable) {
            // A missing table during install must not break anything.
            static::$allLoaded = false;
        }
    }

    public static function flushValueCache(): void
    {
        static::$valueCache = [];
        static::$allLoaded = false;
    }
}
