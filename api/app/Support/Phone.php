<?php

namespace App\Support;

/**
 * The phone number is the member's identity and is never stored in clear.
 * Telegram's contact card gives it in international form ("+971501234567"
 * or "971501234567"); this reduces it to digits, works out the country code,
 * and makes the HMAC the members table is keyed on.
 */
final class Phone
{
    /** Country codes we recognise, longest first so "971" wins over "9". */
    private const COUNTRY_CODES = [
        '971', '251', '966', '974', '965', '973', '968', '249', '291', '254', '255', '256', '234', '233', '212', '213', '216',
        '44', '49', '33', '39', '34', '31', '32', '41', '43', '45', '46', '47', '48', '90', '91', '92', '94', '95', '98', '20', '27', '61', '62', '63', '64', '65', '66', '81', '82', '84', '86',
        '1', '7',
    ];

    /** Bare digits in international form, or null when the value cannot be a number. */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // A UAE number typed locally (0501234567) or an Ethiopian one (0912345678).
        if (strlen($digits) === 10 && str_starts_with($digits, '05')) {
            $digits = '971'.substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '09')) {
            $digits = '251'.substr($digits, 1);
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }

    public static function country(string $digits): string
    {
        foreach (self::COUNTRY_CODES as $code) {
            if (str_starts_with($digits, $code)) {
                return $code;
            }
        }

        return substr($digits, 0, 2);
    }

    public static function hash(string $digits): string
    {
        // Keyed so a copy of the table cannot be reversed by trying every number; StableKey so that
        // the same number hashes the same after a deploy, or "one account per phone" would not hold.
        return hash_hmac('sha256', $digits, StableKey::for('phone'));
    }

    public static function last4(string $digits): string
    {
        return substr($digits, -4);
    }

    /** @return array<int, string> */
    public static function allowedCountries(): array
    {
        $raw = RuntimeEnv::get(null, 'allowed_phone_countries', null, '971,251');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
