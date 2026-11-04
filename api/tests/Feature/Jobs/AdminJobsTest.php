<?php

use App\Console\Commands\ServeWithWorkers;
use App\Http\Controllers\SystemTickController;
use App\Models\Job;
use App\Models\JobSource;
use App\Models\PlatformSetting;
use App\Support\JobSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    fakeClassifier();
});

test('the new admin routes deny a guest', function (string $method, string $path): void {
    $this->json($method, $path)->assertStatus(401);
})->with([
    ['GET', '/api/admin/job-sources'],
    ['POST', '/api/admin/job-sources'],
    ['POST', '/api/admin/job-sources/test'],
    ['GET', '/api/admin/jobs'],
    ['GET', '/api/admin/job-settings'],
    ['PUT', '/api/admin/job-settings'],
]);

test('an admin creates, tests, runs, edits and deletes a source', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    Http::fake(['feeds.example.com/*' => Http::response(RSS)]);

    $this->postJson('/api/admin/job-sources/test', ['kind' => 'rss', 'config' => ['url' => 'https://feeds.example.com/a.xml']])
        ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('found', 2)->assertJsonPath('listings.0.title', 'Housemaid for a family in Al Barsha');

    $this->postJson('/api/admin/job-sources', ['name' => 'Bayt Dubai', 'kind' => 'html', 'config' => ['url' => 'https://bayt.example.com']])
        ->assertStatus(422)->assertJsonValidationErrors(['config.item', 'config.title']);

    $id = $this->postJson('/api/admin/job-sources', ['name' => 'Feed One', 'kind' => 'rss', 'interval_minutes' => 120, 'config' => ['url' => 'https://feeds.example.com/a.xml', 'junk' => 'x']])
        ->assertCreated()->assertJsonPath('source.key', 'feed-one')->json('source.id');
    expect(JobSource::query()->find($id)->config)->toBe(['url' => 'https://feeds.example.com/a.xml']);

    $this->postJson("/api/admin/job-sources/{$id}/run")->assertOk()->assertJsonPath('result.new', 2)->assertJsonPath('source.status', 'ok');
    $this->getJson('/api/admin/job-sources')->assertOk()->assertJsonPath('data.0.last_found', 2);

    JobSource::query()->find($id)->forceFill(['paused_at' => now(), 'failures_in_row' => 3])->save();
    $this->putJson("/api/admin/job-sources/{$id}", ['name' => 'Feed One', 'kind' => 'rss', 'config' => ['url' => 'https://feeds.example.com/b.xml']])
        ->assertOk()->assertJsonPath('source.status', 'ok')->assertJsonPath('source.config.url', 'https://feeds.example.com/b.xml');

    $this->deleteJson("/api/admin/job-sources/{$id}")->assertOk();
    expect(JobSource::query()->find($id))->toBeNull();
});

test('a failing test shows the error instead of saving anything', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    Http::fake(['bayt.example.com/*' => Http::response('<html><body>changed</body></html>')]);

    $this->postJson('/api/admin/job-sources/test', ['kind' => 'html', 'config' => ['url' => 'https://bayt.example.com/jobs', 'item' => '.card', 'title' => 'h2']])
        ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('error', 'No listings matched the item and title selectors. The site may have changed its page.');

    expect(JobSource::query()->count())->toBe(0);
});

test('the review queue: approve with corrections publishes to the channel and alerts', function (): void {
    $channel = jobsChannel();
    Sanctum::actingAs(admin(), ['admin']);
    fakeClassifier(['scam_risk' => 70]);
    $job = ingest(['title' => 'Driver']);

    $this->getJson('/api/admin/jobs')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.status', 'review')->assertJsonPath('counts.review', 1);
    $this->getJson('/api/admin/jobs/'.$job->id)->assertOk()->assertJsonPath('job.raw_text', fn ($t) => str_contains($t, 'Light vehicle driver'));

    $this->postJson('/api/admin/jobs/'.$job->id.'/approve', ['title' => 'Light Vehicle Driver (checked)', 'emirate' => 'sharjah'])
        ->assertOk()->assertJsonPath('job.status', 'published')->assertJsonPath('job.on_channel', true);

    $post = collect(telegramCalls('sendMessage'))->firstWhere('chat_id', $channel);
    // Card labels are in the first card language (Amharic by default); the tag is fixed.
    expect($post['text'])->toContain('Light Vehicle Driver (checked)')->toContain('ሻርጃ')->toContain('#sharjah')
        ->and($job->fresh()->reviewed_by)->not->toBeNull();
});

test('reject with block company adds the company to the blocked list', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    fakeClassifier(['scam_risk' => 70, 'company' => 'Visa King Recruitment']);
    $job = ingest(['company' => 'Visa King Recruitment']);

    $this->postJson('/api/admin/jobs/'.$job->id.'/reject', ['reason' => 'Charges a visa fee', 'block_company' => true])
        ->assertOk()->assertJsonPath('job.status', 'rejected');

    expect(JobSettings::blockedCompanies())->toContain('visa king recruitment');
});

test('closing a published job edits its card instead of deleting it', function (): void {
    $channel = jobsChannel();
    Sanctum::actingAs(admin(), ['admin']);
    $job = ingest();

    $this->postJson('/api/admin/jobs/'.$job->id.'/close')->assertOk()->assertJsonPath('job.status', 'closed');

    $edit = collect(telegramCalls('editMessageText'))->firstWhere('chat_id', $channel);
    expect($edit['text'])->toContain('🔴 ተዘግቷል')
        ->and(collect($edit['reply_markup']['inline_keyboard'])->flatten(1)->all())->toBe([]);
});

test('job settings: saved and read back, the Anthropic key is write-only and validated', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    $base = ['job_review_threshold' => 60, 'job_warning_threshold' => 25, 'job_auto_publish' => true, 'job_card_languages' => 'am,en', 'anthropic_model' => 'claude-opus-5-5'];

    $this->putJson('/api/admin/job-settings', $base + ['job_channel_id' => 'not a channel'])->assertStatus(422)->assertJsonValidationErrors('job_channel_id');
    $this->putJson('/api/admin/job-settings', $base + ['anthropic_api_key' => 'hello'])->assertStatus(422)->assertJsonValidationErrors('anthropic_api_key');

    $response = $this->putJson('/api/admin/job-settings', $base + ['job_channel_id' => '@habesha_jobs', 'job_scam_phrases' => "pay first\n", 'anthropic_api_key' => 'sk-ant-api03-ABCDEFGHIJKLMNOPQRSTUVWXYZ1234'])
        ->assertOk()->assertJsonPath('settings.job_channel_id', '@habesha_jobs')->assertJsonPath('anthropic.configured', true)->assertJsonPath('anthropic.hint', '…1234');

    expect(json_encode($response->json()))->not->toContain('ABCDEFGHIJ')
        ->and(JobSettings::reviewThreshold())->toBe(60)
        ->and(JobSettings::cardLanguages())->toBe(['am', 'en'])
        ->and(JobSettings::scamPhrases())->toBe(['pay first'])
        ->and(PlatformSetting::valueFor(JobSettings::ANTHROPIC_KEY))->not->toContain('sk-ant');

    // A blank key keeps the stored one.
    $this->putJson('/api/admin/job-settings', $base + ['anthropic_api_key' => ''])->assertOk()->assertJsonPath('anthropic.configured', true);
});

test('the dashboard reports jobs and sources', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    ingest();

    $this->getJson('/api/admin/dashboard')->assertOk()
        ->assertJsonPath('jobs.published_today', 1)->assertJsonPath('jobs.classifier', 'rules')->assertJsonPath('sources.active', 1);
});

test('jobs:close-stale closes old jobs and jobs whose page is gone', function (): void {
    $channel = jobsChannel();
    $old = ingest(['url' => 'https://jobs.example.com/view/old']);
    $gone = ingest(['url' => 'https://jobs.example.com/view/gone']);
    $alive = ingest(['url' => 'https://jobs.example.com/view/alive']);

    $old->forceFill(['published_at' => now()->subDays(15)])->save();
    Job::query()->whereIn('id', [$gone->id, $alive->id])->update(['published_at' => now()->subDays(3), 'updated_at' => now()->subHours(7)]);

    Http::fake([
        'jobs.example.com/view/gone' => Http::response('', 404),
        'jobs.example.com/view/alive' => Http::response('', 200),
    ]);

    $this->artisan('jobs:close-stale')->assertSuccessful();

    expect($old->fresh()->status)->toBe('closed')
        ->and($gone->fresh()->status)->toBe('closed')
        ->and($alive->fresh()->status)->toBe('published')
        ->and(count(telegramCalls('editMessageText')))->toBe(2);
});

test('jobs:close-stale runs often but asks about a link at most every six hours, a few at a time', function (): void {
    jobsChannel();
    $jobs = collect(range(1, 12))->map(fn (int $i) => ingest(['url' => "https://jobs.example.com/view/{$i}", 'title' => "Driver number {$i} wanted in Dubai"]));
    Job::query()->whereIn('id', $jobs->pluck('id'))->update(['published_at' => now()->subDays(3), 'updated_at' => now()->subHours(7)]);

    $asked = 0;
    Http::fake(['jobs.example.com/*' => function () use (&$asked) {
        $asked++;

        return Http::response('', 200);
    }]);

    $this->artisan('jobs:close-stale')->assertSuccessful();
    expect($asked)->toBe(10);

    // Fifteen minutes later: only the two it did not reach.
    $this->artisan('jobs:close-stale')->assertSuccessful();
    expect($asked)->toBe(12);

    $this->artisan('jobs:close-stale')->assertSuccessful();
    expect($asked)->toBe(12);
});

test('a link inside a listing is never followed to a private address', function (): void {
    $private = ingest(['url' => 'http://habesha-dubai-rw.db-17.svc.cluster.local:5432/x']);
    Job::query()->whereKey($private->id)->update(['published_at' => now()->subDays(3), 'updated_at' => now()->subHours(7)]);

    Http::fake(['*.local*' => Http::response('', 404)]);
    $this->artisan('jobs:close-stale')->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'cluster.local'));
    expect($private->fresh()->status)->toBe('published');
});

test('with no token saved the tick runs the scheduler once per minute of the clock', function (): void {
    Artisan::shouldReceive('call')->twice()->with('system:tick')->andReturn(0);
    $this->travelTo(now()->startOfMinute()->addSeconds(55));

    $this->postJson('/api/system/tick')->assertOk()->assertJsonPath('ok', true);
    // Whoever asks: what it does is what this minute was going to do anyway, and no more of it.
    $this->postJson('/api/system/tick')->assertStatus(429);

    // A caller late in one minute does not use up the next, in which something may be due.
    $this->travel(10)->seconds();
    $this->postJson('/api/system/tick')->assertOk();
});

test('a run takes the lock, leaves the heartbeat, and gives way to one already running', function (): void {
    Artisan::call('system:tick');
    expect(Cache::get(SystemTickController::LAST_TICK_KEY))->toHaveKeys(['at', 'seconds']);

    Cache::forget(SystemTickController::LAST_TICK_KEY);
    $held = Cache::lock(SystemTickController::LOCK, 60);
    expect($held->get())->toBeTrue();

    Artisan::call('system:tick');
    expect(Cache::get(SystemTickController::LAST_TICK_KEY))->toBeNull();

    // The route says so too, once it is asked with the right to ask.
    $this->postJson('/api/system/tick')->assertStatus(409);
    $held->release();
});

test('the server command is ours, and leaves a Windows machine as it was', function (): void {
    expect(Artisan::all()['serve'])->toBeInstanceOf(ServeWithWorkers::class);
});

test('a token too short to trust closes the tick rather than opening it', function (): void {
    putenv('TICK_TOKEN=short');
    $this->postJson('/api/system/tick')->assertStatus(401);
    putenv('TICK_TOKEN');
});

test('a saved token closes the tick, and the tick then runs the scheduler for whoever has it', function (): void {
    putenv('TICK_TOKEN=tick-token-that-is-long-enough-1234567890');
    $this->postJson('/api/system/tick')->assertStatus(401);
    $this->withHeader('X-Tick-Token', 'wrong')->postJson('/api/system/tick')->assertStatus(401);
    $this->withHeader('X-Tick-Token', 'tick-token-that-is-long-enough-1234567890')->postJson('/api/system/tick')->assertOk()->assertJsonPath('ok', true);
    putenv('TICK_TOKEN');
});
