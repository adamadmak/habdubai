<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;

/**
 * A secret kept in the database (the bot token, the Anthropic key, the
 * scheduler token): encrypted under StableKey, so a deploy cannot lose it.
 * A value saved before this existed is still read through APP_KEY, for as long
 * as that key is the one it was saved under.
 */
final class SecretBox
{
    private const PREFIX = 'box1:';

    public static function seal(string $value): string
    {
        return self::PREFIX.self::encrypter()->encryptString($value);
    }

    /** The secret, or null when it cannot be read any more. */
    public static function open(string $stored): ?string
    {
        try {
            return str_starts_with($stored, self::PREFIX)
                ? self::encrypter()->decryptString(substr($stored, strlen(self::PREFIX)))
                : Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isCurrent(string $stored): bool
    {
        return str_starts_with($stored, self::PREFIX);
    }

    private static function encrypter(): Encrypter
    {
        return new Encrypter(StableKey::for('settings'), 'aes-256-gcm');
    }
}
