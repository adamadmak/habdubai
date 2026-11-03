<?php

use App\Jobs\FetchJobSource;
use App\Jobs\Pipeline\JobIngest;
use App\Jobs\Sources\HtmlFetcher;
use App\Jobs\Sources\RssFetcher;
use App\Models\Job;
use App\Models\JobRaw;
use Illuminate\Support\Facades\Http;

const RSS = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title>Jobs in Dubai</title>
    <item>
      <title>Housemaid for a family in Al Barsha</title>
      <link>https://jobs.example.com/view/101?utm_source=rss</link>
      <guid>job-101</guid>
      <dc:creator>Al Noor Family</dc:creator>
      <description><![CDATA[<p>Live-in housemaid, <b>AED 1,800</b> per month, visa and accommodation provided.</p>]]></description>
      <pubDate>Mon, 02 Nov 2026 08:00:00 +0400</pubDate>
    </item>
    <item>
      <title>Security guard, Sharjah</title>
      <link>https://jobs.example.com/view/102</link>
      <guid>job-102</guid>
      <description>SIRA certified guard for a mall in Sharjah. 12 hour shifts.</description>
    </item>
  </channel>
</rss>
XML;

const ATOM = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Hiring</title>
  <entry>
    <id>tag:example.com,2026:201</id>
    <title>Barista at a cafe in Dubai Marina</title>
    <link rel="alternate" href="/jobs/201"/>
    <author><name>Bean Cafe</name></author>
    <summary>Barista with one year of experience, morning shift.</summary>
    <updated>2026-11-01T10:00:00Z</updated>
  </entry>
</feed>
XML;

const LISTING_PAGE = <<<'HTML'
<html><body>
  <ul class="results">
    <li class="job-card"><a class="title" href="/job/1">Cleaner, Deira</a><span class="co">Sparkle Services</span><span class="loc">Dubai</span><p class="desc">Office cleaning, morning shift.</p></li>
    <li class="job-card"><h3><a href="https://other.example.com/job/2">Waiter for hotel restaurant</a></h3><span class="co">Palm Hotel</span><p class="desc">Fine dining waiter.</p></li>
    <li class="job-card"><span class="co">No title here</span></li>
  </ul>
</body></html>
HTML;

test('an RSS feed becomes listings with clean text, links and dates', function (): void {
    $listings = app(RssFetcher::class)->parse(RSS, 'https://feeds.example.com/jobs.xml');

    expect($listings)->toHaveCount(2)
        ->and($listings[0]->title)->toBe('Housemaid for a family in Al Barsha')
        ->and($listings[0]->company)->toBe('Al Noor Family')
        ->and($listings[0]->externalKey)->toBe('job-101')
        ->and($listings[0]->text)->toContain('AED 1,800 per month')->not->toContain('<b>')
        ->and($listings[0]->postedAt?->toDateString())->toBe('2026-11-02')
        ->and($listings[1]->url)->toBe('https://jobs.example.com/view/102');
});

test('an Atom feed is read too, with relative links made absolute', function (): void {
    $listings = app(RssFetcher::class)->parse(ATOM, 'https://example.com/feed.atom');

    expect($listings)->toHaveCount(1)
        ->and($listings[0]->url)->toBe('https://example.com/jobs/201')
        ->and($listings[0]->company)->toBe('Bean Cafe');
});

test('a page that is not a feed is a clear error', function (): void {
    app(RssFetcher::class)->parse('<html>nope</html>', 'https://example.com/');
})->throws(RuntimeException::class);

test('an HTML page is read with the configured selectors and items without a title are skipped', function (): void {
    $listings = app(HtmlFetcher::class)->parse(LISTING_PAGE, 'https://jobs.example.com/search?q=dubai', [
        'item' => 'li.job-card', 'title' => '.title, h3 a', 'company' => '.co', 'location' => '.loc', 'snippet' => '.desc',
    ]);

    expect($listings)->toHaveCount(2)
        ->and($listings[0]->title)->toBe('Cleaner, Deira')
        ->and($listings[0]->url)->toBe('https://jobs.example.com/job/1')
        ->and($listings[0]->company)->toBe('Sparkle Services')
        ->and($listings[1]->url)->toBe('https://other.example.com/job/2');
});

test('selectors that match nothing are a failure, not an empty success', function (): void {
    app(HtmlFetcher::class)->parse(LISTING_PAGE, 'https://jobs.example.com/', ['item' => '.nothing', 'title' => '.title']);
})->throws(RuntimeException::class, 'No listings matched');

test('a fetch stores new listings once and runs them through the pipeline', function (): void {
    Http::fake(['feeds.example.com/*' => Http::response(RSS)]);
    $source = jobSource();

    $first = (new FetchJobSource($source->id))->handle(app(JobIngest::class));
    $second = (new FetchJobSource($source->id))->handle(app(JobIngest::class));

    expect($first)->toBe(['found' => 2, 'new' => 2, 'error' => null])
        ->and($second['new'])->toBe(0)
        ->and(JobRaw::query()->count())->toBe(2)
        ->and(JobRaw::query()->whereNull('processed_at')->count())->toBe(0)
        ->and(Job::query()->count())->toBe(2)
        ->and($source->fresh()->last_success_at)->not->toBeNull();

    // No Anthropic key in tests: the rules classifier ran.
    $maid = Job::query()->where('title', 'like', 'Housemaid%')->first();
    expect($maid->classified_by)->toBe('rules')
        ->and($maid->category)->toBe('domestic')
        ->and($maid->salary_min)->toBe(1800);
});

test('three failed runs in a row pause a source', function (): void {
    Http::fake(['feeds.example.com/*' => Http::response('down', 503)]);
    $source = jobSource();

    foreach (range(1, 3) as $i) {
        $result = (new FetchJobSource($source->id))->handle(app(JobIngest::class));
    }

    $source->refresh();
    expect($result['error'])->toContain('HTTP 503')
        ->and($source->failures_in_row)->toBe(3)
        ->and($source->paused_at)->not->toBeNull()
        ->and($source->isDue())->toBeFalse();
});

test('jobs:fetch-due queues only sources whose interval has passed', function (): void {
    Http::fake(['feeds.example.com/*' => Http::response(RSS)]);
    $due = jobSource();
    $recent = jobSource();
    $recent->forceFill(['last_run_at' => now()->subMinutes(5)])->save();
    $paused = jobSource();
    $paused->forceFill(['paused_at' => now()])->save();

    $this->artisan('jobs:fetch-due')->assertSuccessful();

    expect($due->fresh()->last_success_at)->not->toBeNull()
        ->and($recent->fresh()->last_success_at)->toBeNull()
        ->and($paused->fresh()->last_success_at)->toBeNull();
});
