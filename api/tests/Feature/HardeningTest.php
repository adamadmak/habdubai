<?php

use App\Bot\BotMessages;
use App\Jobs\ProcessJobListing;
use App\Models\Announcement;
use App\Models\AnnouncementTopic;
use App\Models\Job;
use App\Models\JobRaw;
use App\Models\TelegramUpdate;
use Laravel\Sanctum\Sanctum;

// ---- what the bot keeps --------------------------------------------------------------

test('a handled update keeps only its id and kind: the phone number on a contact card is gone', function (): void {
    joinMember(9501, '+971500009501');

    $stored = TelegramUpdate::query()->get();
    expect($stored)->not->toBeEmpty()
        ->and($stored->every(fn (TelegramUpdate $u) => $u->processed_at !== null && ! empty($u->payload['redacted'])))->toBeTrue()
        ->and(json_encode($stored->pluck('payload')))->not->toContain('500009501')->not->toContain('phone_number');
});

test('an update whose handler died is run again; a week-old one is deleted', function (): void {
    $lost = TelegramUpdate::query()->create([
        'update_id' => nextUpdateId(),
        'payload' => telegramMessage(9502, ['text' => '/start']),
        'received_at' => now()->subMinutes(5),
    ]);
    $old = TelegramUpdate::query()->create([
        'update_id' => nextUpdateId(),
        'payload' => ['update_id' => 1, 'kind' => 'message', 'redacted' => true],
        'received_at' => now()->subDays(8),
        'processed_at' => now()->subDays(8),
    ]);

    $this->artisan('bot:updates-chores')->assertSuccessful();

    expect($lost->fresh()->processed_at)->not->toBeNull()
        ->and($lost->fresh()->attempts)->toBe(1)
        ->and(textsTo(9502))->not->toBeEmpty()
        ->and(TelegramUpdate::query()->find($old->id))->toBeNull();
});

test('an edit in a private chat is ignored instead of replaying a step', function (): void {
    joinMember(9503, '+971500009503');
    $before = count(sentMessages());

    $this->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'edited_message' => telegramMessage(9503, ['text' => '/ask'])['message'],
    ])->assertOk();

    expect(count(sentMessages()))->toBe($before);
});

// ---- ids, search, limits -----------------------------------------------------------------

test('an id too long for the database is a 404, not a server error', function (): void {
    $this->getJson('/api/public/jobs/99999999999999999999')->assertNotFound();
    $this->getJson('/api/public/announcements/99999999999999999999')->assertNotFound();

    Sanctum::actingAs(admin(), ['admin']);
    $this->getJson('/api/admin/members/99999999999999999999')->assertNotFound();
});

test('the news list finds an announcement by words in any of its languages', function (): void {
    $topic = AnnouncementTopic::query()->where('key', 'platform')->firstOrFail();
    foreach (['Consulate passport day on Sunday', 'Football tournament in Deira'] as $text) {
        Announcement::query()->forceCreate([
            'topic_id' => $topic->id, 'original_language' => 'en', 'text_en' => $text, 'text_am' => $text, 'text_ti' => $text,
            'status' => 'published', 'published_at' => now(),
        ]);
    }

    $this->getJson('/api/public/announcements?q=PASSPORT')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.text.en', 'Consulate passport day on Sunday');
});

test('admin sign-in is limited per email, so a stranger cannot lock everyone out', function (): void {
    admin();

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong-'.$i])->assertStatus(422);
    }
    $this->postJson('/api/admin/login', ['email' => 'admin@example.com', 'password' => 'correct-horse-battery'])->assertStatus(429);

    // Another administrator signs in as usual.
    \App\Models\User::query()->create(['name' => 'Second', 'email' => 'second@example.com', 'password' => 'another-long-password']);
    $this->postJson('/api/admin/login', ['email' => 'second@example.com', 'password' => 'another-long-password'])->assertOk();
});

test('a bot text Telegram would refuse is not saved', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    $key = array_key_first(BotMessages::defaults());

    $this->putJson("/api/admin/bot-texts/{$key}", ['language' => 'en', 'text' => 'Prices < 100 AED'])
        ->assertStatus(422)->assertJsonPath('errors.text.0', 'Write < as &lt; and > as &gt; outside a tag.');
    $this->putJson("/api/admin/bot-texts/{$key}", ['language' => 'en', 'text' => 'Hello <b>{name}</b'])->assertStatus(422);
    $this->putJson("/api/admin/bot-texts/{$key}", ['language' => 'en', 'text' => 'Prices &lt; 100 AED, <b>today</b> only'])->assertOk();
});

// ---- jobs ----------------------------------------------------------------------------------

test('a listing processed a second time makes no second job', function (): void {
    fakeClassifier();
    $job = ingest(['title' => 'Delivery driver', 'company' => 'Fast Logistics']);
    expect($job)->not->toBeNull();

    // A retry that arrives while the first run has not yet marked the listing done.
    JobRaw::query()->whereKey($job->raw_id)->update(['processed_at' => null]);
    ProcessJobListing::dispatchSync($job->raw_id);

    expect(Job::query()->where('raw_id', $job->raw_id)->count())->toBe(1);
});

// ---- website visits ------------------------------------------------------------------------

test('website visits are counted per day and page, and the dashboard reads them back', function (): void {
    $this->postJson('/api/public/pageview', ['path' => '/am/jobs'])->assertNoContent();
    $this->postJson('/api/public/pageview', ['path' => '/am/jobs/'])->assertNoContent();
    $this->postJson('/api/public/pageview', ['path' => '/en'])->assertNoContent();
    $this->postJson('/api/public/pageview', ['path' => 'https://elsewhere.example/'])->assertStatus(422);
    $this->postJson('/api/public/pageview', ['path' => '/en/jobs?q=x'])->assertStatus(422);

    Sanctum::actingAs(admin(), ['admin']);
    $stats = $this->getJson('/api/admin/analytics?days=7')->assertOk()->json();

    expect($stats['total'])->toBe(3)
        ->and($stats['days'])->toHaveCount(7)
        ->and(end($stats['days']))->toBe(['day' => now('Asia/Dubai')->toDateString(), 'views' => 3])
        ->and($stats['top'][0])->toBe(['path' => '/am/jobs', 'views' => 2]);
});
