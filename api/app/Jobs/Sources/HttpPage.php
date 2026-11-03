<?php

namespace App\Jobs\Sources;

use App\Support\PublicAddress;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** A polite GET: a named user agent, a short timeout, one retry, and a size cap. */
final class HttpPage
{
    private const MAX_BYTES = 3_000_000;

    public static function get(string $url): string
    {
        if (! PublicAddress::allows($url)) {
            throw new RuntimeException('Could not use '.parse_url($url, PHP_URL_HOST).': it is not an address on the public internet.');
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'HabeshaDubaiBot/1.0 (+community job alerts; summary and link only)',
                'Accept' => 'text/html,application/xhtml+xml,application/rss+xml,application/atom+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en',
            ])->withOptions(['allow_redirects' => [
                'max' => 3,
                'protocols' => ['http', 'https'],
                // A public page must not be able to send the fetch somewhere private.
                'on_redirect' => function ($request, $response, $uri): void {
                    if (! PublicAddress::allows((string) $uri)) {
                        throw new RuntimeException('The page redirects to an address that is not on the public internet.');
                    }
                },
            ]])->timeout(20)->connectTimeout(8)->retry(1, 1500, throw: false)->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach '.parse_url($url, PHP_URL_HOST).': '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().' from '.parse_url($url, PHP_URL_HOST).'.');
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            $body = substr($body, 0, self::MAX_BYTES);
        }

        return $body;
    }

    /** Make a link from a page absolute. */
    public static function absolute(?string $href, string $base): ?string
    {
        $href = trim((string) $href);

        if ($href === '' || str_starts_with($href, 'javascript:') || str_starts_with($href, '#')) {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (str_starts_with($href, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $path = $parts['path'] ?? '/';
        $dir = str_ends_with($path, '/') ? $path : (dirname($path) === '\\' ? '/' : rtrim(dirname($path), '/').'/');

        return $origin.$dir.$href;
    }
}
