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

    /** Telegram fetches pictures from, and opens inside itself, only a public https address. */
    public static function siteIsPublic(): bool
    {
        $site = self::site();
        $host = (string) parse_url($site, PHP_URL_HOST);

        return str_starts_with($site, 'https://') && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * A button in a private chat that opens a page of the website: inside
     * Telegram when it can, where the member is signed in without typing a code;
     * as an ordinary link otherwise. Not for a channel or a group, where Telegram
     * refuses a message with this kind of button.
     *
     * @return array{text: string, web_app?: array{url: string}, url?: string}
     */
    public static function siteButton(string $text, string $url): array
    {
        return self::siteIsPublic() ? ['text' => $text, 'web_app' => ['url' => $url]] : ['text' => $text, 'url' => $url];
    }

    /** Telegram fetches photos by URL only from a public https address. */
    public static function apiIsPublic(): bool
    {
        $host = (string) parse_url(self::api(), PHP_URL_HOST);

        return str_starts_with(self::api(), 'https://') && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
