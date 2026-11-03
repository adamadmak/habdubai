<?php

namespace App\Jobs\Pipeline;

use App\Models\Job;
use App\Models\JobRaw;

/**
 * The same job is posted on several sites and reposted every few days. A
 * listing is a duplicate when its URL matches one we already have, when its
 * title and company match one from the last seven days, or when its text is
 * nearly the same as one from the last seven days. Runs before the model is
 * called, so a duplicate costs nothing.
 */
class Deduper
{
    private const SIMILARITY = 0.8;

    private const WINDOW_DAYS = 7;

    public function duplicateOf(JobRaw $raw): ?Job
    {
        $urlKey = self::urlKey($raw->url);

        if ($urlKey !== null) {
            $byUrl = Job::query()->where('url_key', $urlKey)->whereNull('duplicate_of')->oldest('id')->first();
            if ($byUrl) {
                return $byUrl;
            }
        }

        $since = now()->subDays(self::WINDOW_DAYS);
        $fingerprint = self::fingerprint($raw->title, $raw->company);

        if ($fingerprint !== null) {
            $byFingerprint = Job::query()->where('fingerprint', $fingerprint)->whereNull('duplicate_of')
                ->where('created_at', '>=', $since)->oldest('id')->first();
            if ($byFingerprint) {
                return $byFingerprint;
            }
        }

        $shingles = self::shingles($raw->raw_text);

        if (count($shingles) < 5) {
            return null;
        }

        $candidates = Job::query()->with('raw:id,raw_text')->whereNull('duplicate_of')->whereNotNull('raw_id')
            ->where('created_at', '>=', $since)->latest('id')->limit(500)->get();

        foreach ($candidates as $candidate) {
            if ($candidate->raw && self::jaccard($shingles, self::shingles($candidate->raw->raw_text)) >= self::SIMILARITY) {
                return $candidate;
            }
        }

        return null;
    }

    /** The URL without tracking parameters, fragment or trailing slash, hashed. */
    public static function urlKey(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));
        if (! isset($parts['host'])) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        $query = array_filter($query, fn ($key) => ! preg_match('/^(utm_|ref|source|src|fbclid|gclid|trk|from)/i', (string) $key), ARRAY_FILTER_USE_KEY);
        ksort($query);

        $normal = strtolower(preg_replace('/^www\./', '', $parts['host'])).rtrim($parts['path'] ?? '', '/').($query ? '?'.http_build_query($query) : '');

        return hash('sha256', $normal);
    }

    public static function fingerprint(?string $title, ?string $company): ?string
    {
        $norm = fn (?string $v) => trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $v)) ?? '');
        $title = $norm($title);

        if ($title === '') {
            return null;
        }

        return hash('sha256', $title.'|'.$norm($company));
    }

    /** @return array<string, true> word 3-grams */
    public static function shingles(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $set = [];

        for ($i = 0, $n = count($words) - 2; $i < $n; $i++) {
            $set[$words[$i].' '.$words[$i + 1].' '.$words[$i + 2]] = true;
        }

        return $set;
    }

    /** @param array<string, true> $a @param array<string, true> $b */
    public static function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $intersection = count(array_intersect_key($a, $b));

        return $intersection / (count($a) + count($b) - $intersection);
    }
}
