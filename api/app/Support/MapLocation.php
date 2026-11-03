<?php

namespace App\Support;

/**
 * Reads a place from what a member sends: a Telegram location pin, or a
 * Google Maps link. A short link (maps.app.goo.gl) carries no coordinates;
 * it is kept as the link, which opens the same place.
 */
final class MapLocation
{
    /** @return array{latitude: ?float, longitude: ?float, maps_url: ?string}|null null when the text is not a maps link */
    public static function fromLink(string $text): ?array
    {
        $text = trim($text);

        if (! preg_match('#^https?://(www\.)?(google\.[a-z.]+/maps|maps\.google\.[a-z.]+|maps\.app\.goo\.gl|goo\.gl/maps)\S*$#i', $text)) {
            return null;
        }

        $lat = $lng = null;

        if (preg_match('#@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)#', $text, $m)
            || preg_match('#[?&](?:q|query|ll|destination)=(-?\d{1,2}\.\d+),\s*(-?\d{1,3}\.\d+)#', urldecode($text), $m)) {
            [$lat, $lng] = [(float) $m[1], (float) $m[2]];
        }

        if ($lat !== null && ! self::valid($lat, $lng)) {
            $lat = $lng = null;
        }

        return ['latitude' => $lat, 'longitude' => $lng, 'maps_url' => mb_substr($text, 0, 500)];
    }

    public static function valid(?float $lat, ?float $lng): bool
    {
        return $lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180 && ! ($lat == 0.0 && $lng == 0.0);
    }

    /** A business phone as digits with a plus, or null. UAE local numbers get +971. */
    public static function phone(string $text): ?string
    {
        $digits = preg_replace('/\D+/', '', $text) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) >= 9 && strlen($digits) <= 10) {
            $digits = '971'.substr($digits, 1);
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
    }
}
