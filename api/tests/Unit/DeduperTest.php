<?php

use App\Jobs\Pipeline\Deduper;
use App\Support\JobSettings;

test('tracking parameters, www, case and trailing slashes do not change the URL key', function (): void {
    expect(Deduper::urlKey('https://www.Jobs.example.com/view/12/?utm_source=x&ref=tg'))
        ->toBe(Deduper::urlKey('https://jobs.example.com/view/12'))
        ->and(Deduper::urlKey('https://jobs.example.com/view/12?id=3'))->not->toBe(Deduper::urlKey('https://jobs.example.com/view/12?id=4'))
        ->and(Deduper::urlKey(null))->toBeNull()
        ->and(Deduper::urlKey('not a url'))->toBeNull();
});

test('the fingerprint ignores punctuation and case', function (): void {
    expect(Deduper::fingerprint('Security Guard!', 'SAFE co'))->toBe(Deduper::fingerprint('security guard', 'Safe Co'))
        ->and(Deduper::fingerprint('', 'Safe Co'))->toBeNull();
});

test('text similarity is high for a repost and low for a different job', function (): void {
    $a = Deduper::shingles('Experienced driver needed in Dubai with a valid UAE licence for deliveries across the city');
    $b = Deduper::shingles('Experienced driver needed in Dubai with a valid UAE licence for deliveries across the city today');
    $c = Deduper::shingles('Nanny wanted for two children in Sharjah, live in, Ethiopian or Filipino preferred');

    expect(Deduper::jaccard($a, $b))->toBeGreaterThan(0.8)
        ->and(Deduper::jaccard($a, $c))->toBeLessThan(0.1);
});

test('company names normalise without legal suffixes', function (): void {
    expect(JobSettings::normaliseCompany('Shady Recruiters L.L.C.'))->toBe('shady recruiters')
        ->and(JobSettings::normaliseCompany('Desert Haulage FZE'))->toBe('desert haulage');
});
