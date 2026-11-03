<?php

namespace App\Support;

/**
 * The two public addresses: the API (for photo links the website and
 * Telegram load) and the website (for buttons in the channel and the bot).
 * Both come from Configuration → Settings, because the config cache on this
 * host is built without the runtime environment.
 */
final class PublicUrls
{
    /** The API's public address: the saved one, else the address this request came in on. */
    public static function api(string $path = ''): string
    {
        $base = rtrim(RuntimeEnv::get(null, 'public_url', null), '/');

        if ($base === '') {
            $request = app()->bound('request') ? request() : null;
            $base = $request && $request->getHost() !== ''
                ? rtrim($request->getSchemeAndHttpHost(), '/')
                : rtrim((string) config('app.url'), '/');
        }

        return $path === '' ? $base : $base.'/'.ltrim($path, '/');
    }

    /** The website's address, or '' when it has not been set yet. */
    public static function site(string $path = ''): string
    {
        $base = rtrim(RuntimeEnv::get(null, 'site_url', null, (string) config('services.host.site_url')), '/');

        if ($base === '') {
            return '';
        }

        return $path === '' ? $base : $base.'/'.ltrim($path, '/');
    }

    /** Telegram fetches photos by URL only from a public https address. */
    public static function apiIsPublic(): bool
    {
        $host = (string) parse_url(self::api(), PHP_URL_HOST);

        return str_starts_with(self::api(), 'https://') && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
