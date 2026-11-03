<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Who is really asking. The public website calls this API from its own
 * server, so every visitor would otherwise share the server's address and
 * one rate-limit bucket. The site sends a shared key (SITE_API_KEY on both
 * apps) with the visitor's address and browser; only with that key are they
 * believed.
 */
final class ClientIp
{
    public static function siteKeyValid(Request $request): bool
    {
        $expected = RuntimeEnv::get('services.host.site_api_key', null, 'SITE_API_KEY');
        $given = (string) $request->header('X-Site-Key', '');

        return $expected !== '' && strlen($expected) >= 24 && hash_equals($expected, $given);
    }

    /** The visitor's address: forwarded by the website when it proves itself, else the connection's. */
    public static function of(Request $request): string
    {
        if (self::siteKeyValid($request)) {
            $forwarded = trim((string) $request->header('X-Client-Ip', ''));
            if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
                return $forwarded;
            }
        }

        return (string) $request->ip();
    }

    public static function agent(Request $request): ?string
    {
        $agent = self::siteKeyValid($request) ? $request->header('X-Client-Agent') : $request->userAgent();

        return $agent !== null && $agent !== '' ? mb_substr((string) $agent, 0, 200) : null;
    }
}
