<?php

use App\Http\Controllers\SystemTickController;
use App\Models\Business;
use App\Models\DirectoryCity;
use App\Models\KnowledgeArticle;
use App\Models\PlatformSetting;
use App\Models\TelegramUpdate;
use App\Support\JobSettings;
use App\Support\RuntimeEnv;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// ---- GET /api/health ------------------------------------------------------------------

test('health answers with numbers only, never cached', function (): void {
    $response = $this->getJson('/api/health')->assertOk();

    expect($response->json())->toBe(['ok' => true, 'db' => true, 'tick_age_seconds' => null, 'queue_pending' => 0, 'failed_updates_24h' => 0, 'workers' => 1])
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('health reports the age of the last tick and the updates that failed in the last day', function (): void {
    $this->travelTo(now()->startOfMinute());
    Cache::forever(SystemTickController::LAST_TICK_KEY, ['at' => now()->subSeconds(45)->toIso8601String(), 'seconds' => 3.2]);

    foreach ([[now()->subHours(2), 'Bad Request: message is too long', null], [now()->subDays(2), 'old', null], [now()->subHour(), null, now()]] as [$at, $error, $done]) {
        TelegramUpdate::query()->create(['update_id' => nextUpdateId(), 'payload' => ['update_id' => 1], 'received_at' => $at, 'error' => $error, 'processed_at' => $done]);
    }

    $this->getJson('/api/health')->assertOk()
        ->assertJsonPath('tick_age_seconds', 45)
        ->assertJsonPath('failed_updates_24h', 1);
});

test('health answers 503 when the database cannot be reached', function (): void {
    config([
        'database.connections.unreachable' => ['driver' => 'sqlite', 'database' => storage_path('no-such-dir/none.sqlite'), 'prefix' => ''],
        'database.default' => 'unreachable',
    ]);

    try {
        $response = $this->getJson('/api/health');
    } finally {
        config(['database.default' => 'sqlite']);
    }

    $response->assertStatus(503);
    expect($response->json())->toBe(['ok' => false, 'db' => false, 'tick_age_seconds' => null, 'queue_pending' => 0, 'failed_updates_24h' => 0, 'workers' => 1])
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('health is limited to sixty calls a minute per address', function (): void {
    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/health')->assertOk();
    }

    $this->getJson('/api/health')->assertStatus(429)->assertHeader('Retry-After');
});

// ---- GET /api/admin/readiness ---------------------------------------------------------------

/** Everything a launch needs, set the way an operator would set it, with Telegram agreeing. */
function launchReady(): array
{
    $secrets = ['tick' => str_repeat('t', 40), 'site' => str_repeat('s', 40), 'anthropic' => 'sk-ant-'.str_repeat('k', 32)];

    foreach ([
        'public_url' => 'https://api.habesha.example',
        'site_url' => 'https://habesha.example',
        'job_channel_id' => '@habesha_jobs',
        'telegram_questions_group_id' => '-1001001',
        'telegram_goods_group_id' => '-1001002',
        'support_telegram_username' => 'habesha_help',
        'safety_consulate_phone' => '+971 4 000 0000',
        'safety_mohre_phone' => '80060',
    ] as $key => $value) {
        PlatformSetting::put($key, $value);
    }
    PlatformSetting::put('tick_token', RuntimeEnv::conceal($secrets['tick']));
    PlatformSetting::put('site_api_key', RuntimeEnv::conceal($secrets['site']));
    JobSettings::setAnthropicKey($secrets['anthropic']);

    TelegramFake::$results['getMe'] = ['id' => 777001, 'is_bot' => true, 'username' => 'HabeshaDubaiBot'];
    TelegramFake::$results['getWebhookInfo'] = ['url' => 'https://api.habesha.example/api/telegram/webhook/'.webhookSecret(), 'pending_update_count' => 0];
    TelegramFake::$results['getChatMember'] = ['status' => 'administrator', 'can_delete_messages' => true, 'can_restrict_members' => true, 'can_manage_topics' => true, 'can_post_messages' => true];
    TelegramFake::$results['getChat'] = ['id' => -1001001, 'type' => 'supergroup', 'is_forum' => true];

    Cache::forever(SystemTickController::LAST_TICK_KEY, ['at' => now()->subSeconds(20)->toIso8601String(), 'seconds' => 1.5]);
    jobSource();
    foreach (range(1, 3) as $i) {
        KnowledgeArticle::query()->create(['title' => 'Article '.$i, 'body' => 'Checked guidance number '.$i, 'active' => true]);
    }
    $city = DirectoryCity::query()->firstOrFail();
    foreach (range(1, 5) as $i) {
        Business::query()->create(['city_id' => $city->id, 'category' => 'restaurant', 'name' => 'Habesha Kitchen '.$i, 'area' => 'Deira', 'status' => 'approved']);
    }
    config(['app.debug' => false]);

    return $secrets;
}

test('the checklist is for administrators only', function (): void {
    $this->getJson('/api/admin/readiness')->assertUnauthorized();
});

test('a fully set up installation is ready, scores 100, and shows no secret', function (): void {
    $secrets = launchReady();
    Sanctum::actingAs(admin(), ['admin']);

    $response = $this->getJson('/api/admin/readiness')->assertOk();
    $failing = collect($response->json('items'))->where('ok', false)->pluck('detail', 'key')->all();

    expect($failing)->toBe([])
        ->and($response->json('ready'))->toBeTrue()
        ->and($response->json('score'))->toBe(100)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $body = $response->getContent();
    foreach ([...array_values($secrets), webhookSecret(), '1234567:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdef'] as $secret) {
        expect($body)->not->toContain($secret);
    }
});

test('every item says what it is, how bad, what is wrong, how to fix it and where', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    config(['app.debug' => false]);

    $response = $this->getJson('/api/admin/readiness')->assertOk();
    $items = collect($response->json('items'));

    expect($items->pluck('key')->all())->toBe([
        'bot_token', 'webhook', 'public_url', 'site_url', 'scheduler', 'tick_token', 'site_api_key', 'jobs_channel',
        'questions_group', 'goods_group', 'job_sources', 'anthropic_key', 'admin_user', 'support_username', 'safety_phones',
        'knowledge', 'migrations', 'queue_backlog', 'failed_updates', 'directory', 'announcement_topics', 'debug_off',
    ]);

    $items->each(function (array $item): void {
        expect(array_keys($item))->toBe(['key', 'label', 'ok', 'severity', 'detail', 'fix', 'href'])
            ->and($item['severity'])->toBeIn(['blocker', 'important', 'nice'])
            ->and($item['label'])->not->toBe('')
            ->and($item['detail'])->not->toBe('')
            ->and($item['href'] === null || str_starts_with($item['href'], '/'))->toBeTrue()
            ->and($item['ok'] ? $item['fix'] === '' : $item['fix'] !== '')->toBeTrue();
    });

    // A fresh install: no webhook, addresses, tick, channel or groups yet.
    $failingBlockers = $items->where('severity', 'blocker')->where('ok', false)->pluck('key')->all();
    expect($response->json('ready'))->toBeFalse()
        ->and($failingBlockers)->toContain('webhook', 'public_url', 'scheduler', 'jobs_channel', 'questions_group')
        ->and($items->firstWhere('key', 'migrations')['ok'])->toBeTrue()
        ->and($items->firstWhere('key', 'admin_user')['ok'])->toBeTrue()
        ->and($items->firstWhere('key', 'announcement_topics')['ok'])->toBeTrue();

    // Blockers weigh 3, important 2, nice 1.
    $weights = ['blocker' => 3, 'important' => 2, 'nice' => 1];
    $total = $items->sum(fn (array $i) => $weights[$i['severity']]);
    $passed = $items->where('ok', true)->sum(fn (array $i) => $weights[$i['severity']]);
    expect($response->json('score'))->toBe((int) round(100 * $passed / $total));
});

test('a bot without the rights it needs, Topics off, or a webhook elsewhere is a blocker with its fix', function (): void {
    launchReady();
    TelegramFake::$results['getChatMember'] = ['status' => 'administrator', 'can_delete_messages' => false, 'can_restrict_members' => true, 'can_manage_topics' => true, 'can_post_messages' => true];
    TelegramFake::$results['getChat'] = ['id' => -1001001, 'type' => 'supergroup', 'is_forum' => false];
    TelegramFake::$results['getWebhookInfo'] = ['url' => 'https://old-host.example/api/telegram/webhook/'.webhookSecret(), 'pending_update_count' => 12];
    Sanctum::actingAs(admin(), ['admin']);

    $items = collect($this->getJson('/api/admin/readiness')->assertOk()->assertJsonPath('ready', false)->json('items'))->keyBy('key');

    expect($items['questions_group']['ok'])->toBeFalse()
        ->and($items['questions_group']['detail'])->toContain('delete messages')
        ->and($items['questions_group']['href'])->toBe('/settings')
        ->and($items['webhook']['ok'])->toBeFalse()
        ->and($items['webhook']['detail'])->toContain('old-host.example')->not->toContain(webhookSecret())
        ->and($items['jobs_channel']['ok'])->toBeTrue();

    // The rights fixed, Topics still off: the questions group says so.
    TelegramFake::$results['getChatMember']['can_delete_messages'] = true;
    $items = collect($this->getJson('/api/admin/readiness')->json('items'))->keyBy('key');
    expect($items['questions_group']['detail'])->toContain('Topics')
        ->and($items['goods_group']['ok'])->toBeTrue();
});

test('a channel chosen as a community group is named as the problem, not as a missing right', function (): void {
    launchReady();
    TelegramFake::$results['getChat'] = ['id' => -1001001, 'type' => 'channel'];
    Sanctum::actingAs(admin(), ['admin']);

    $items = collect($this->getJson('/api/admin/readiness')->assertOk()->assertJsonPath('ready', false)->json('items'))->keyBy('key');

    expect($items['questions_group']['ok'])->toBeFalse()
        ->and($items['questions_group']['detail'])->toContain('channel')->not->toContain('manage topics')
        ->and($items['questions_group']['fix'])->toContain('create a group')
        ->and($items['jobs_channel']['ok'])->toBeTrue();
});

test('a stopped scheduler, a long queue and failed updates are reported', function (): void {
    launchReady();
    Cache::forever(SystemTickController::LAST_TICK_KEY, ['at' => now()->subMinutes(10)->toIso8601String(), 'seconds' => 1]);
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert(array_fill(0, 200, ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]));
    TelegramUpdate::query()->create(['update_id' => nextUpdateId(), 'payload' => ['update_id' => 1], 'received_at' => now()->subHour(), 'error' => 'boom']);
    Sanctum::actingAs(admin(), ['admin']);

    $items = collect($this->getJson('/api/admin/readiness')->assertOk()->assertJsonPath('ready', false)->json('items'))->keyBy('key');

    expect($items['scheduler']['ok'])->toBeFalse()
        ->and($items['scheduler']['detail'])->toContain('10 minutes')
        ->and($items['queue_backlog']['ok'])->toBeFalse()
        ->and($items['queue_backlog']['detail'])->toContain('200')
        ->and($items['failed_updates']['ok'])->toBeFalse()
        ->and($items['failed_updates']['detail'])->not->toContain('boom');
});

test('what Telegram says about the bot is kept for ten minutes', function (): void {
    launchReady();
    Sanctum::actingAs(admin(), ['admin']);

    $this->getJson('/api/admin/readiness')->assertOk();
    $this->getJson('/api/admin/readiness')->assertOk();
    expect(telegramCalls('getMe'))->toHaveCount(1);

    $this->travel(11)->minutes();
    Cache::forever(SystemTickController::LAST_TICK_KEY, ['at' => now()->toIso8601String(), 'seconds' => 1]);
    $this->getJson('/api/admin/readiness')->assertOk()->assertJsonPath('ready', true);
    expect(telegramCalls('getMe'))->toHaveCount(2);
});

test('a refused bot token fails the token item and the checks that need it, without leaking the token', function (): void {
    launchReady();
    TelegramFake::$errors['getMe'] = [401, 'Unauthorized'];
    Sanctum::actingAs(admin(), ['admin']);

    $response = $this->getJson('/api/admin/readiness')->assertOk()->assertJsonPath('ready', false);
    $items = collect($response->json('items'))->keyBy('key');

    expect($items['bot_token']['ok'])->toBeFalse()
        ->and($items['bot_token']['detail'])->toContain('Unauthorized')
        ->and($items['jobs_channel']['ok'])->toBeFalse()
        ->and($response->getContent())->not->toContain('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdef');
});
