<?php

namespace App\Jobs\Sources;

/**
 * What a page says about itself to anything that shows a link: its title, description and
 * picture. So a post that is only a link can still become a job with a title and an image.
 */
final class LinkPreview
{
    /** @return array{title: ?string, description: ?string, image: ?string}|null null when the page cannot be read */
    public static function read(string $url): ?array
    {
        try {
            $html = HttpPage::get($url);
        } catch (\Throwable) {
            return null;
        }

        $preview = [
            'title' => self::meta($html, 'og:title'),
            'description' => self::meta($html, 'og:description'),
            'image' => self::meta($html, 'og:image'),
        ];

        if ($preview['image'] !== null && ! preg_match('#^https://#i', $preview['image'])) {
            $preview['image'] = null;
        }

        return array_filter($preview) === [] ? null : $preview;
    }

    /** The first line a person would call the job, without the "We're hiring!" in front of it. */
    public static function headline(array $preview): ?string
    {
        foreach ([$preview['description'] ?? null, $preview['title'] ?? null] as $text) {
            $line = trim((string) strtok((string) $text, "\n"));
            $line = trim(preg_replace('/^\W*(we.?re|we are)?\s*hiring\W*/iu', '', $line) ?? $line);
            $line = trim(preg_replace('/#[\p{L}\p{N}_]+/u', '', $line) ?? $line, " |·-–\t");

            if (mb_strlen($line) >= 4) {
                return mb_substr($line, 0, 120);
            }
        }

        return null;
    }

    private static function meta(string $html, string $property): ?string
    {
        $name = preg_quote($property, '/');

        if (! preg_match('/<meta\b[^>]*\b(?:property|name)=["\']'.$name.'["\'][^>]*\bcontent=(["\'])(.*?)\1/isu', $html, $m)
            && ! preg_match('/<meta\b[^>]*\bcontent=(["\'])(.*?)\1[^>]*\b(?:property|name)=["\']'.$name.'["\']/isu', $html, $m)) {
            return null;
        }

        $value = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value === '' ? null : mb_substr($value, 0, 1000);
    }
}
