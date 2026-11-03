<?php

namespace App\Support;

/**
 * A key that survives a deploy. APP_KEY does not on this host: each build can
 * come up with its own, and a value kept under the old one is lost. That made
 * the saved bot token unreadable after a deploy (Telegram got 403 on every
 * delivery until someone saved it again), and would have let the same phone
 * number verify a second account.
 *
 * So what must outlast a deploy is keyed from the database password, the one
 * secret that stays the same for as long as the data itself does, and which a
 * copy of the database does not contain. Where there is no password (SQLite on
 * a laptop, the tests) it falls back to APP_KEY, which is stable there.
 */
final class StableKey
{
    /** 32 raw bytes, different for each purpose. */
    public static function for(string $purpose): string
    {
        $connection = (string) config('database.default');
        $password = (string) config("database.connections.{$connection}.password");

        return hash_hmac('sha256', 'habesha-dubai|'.$purpose, $password !== '' ? $password : (string) config('app.key'), true);
    }
}
