<?php

use App\Models\Member;
use App\Models\PlatformSetting;
use App\Models\TelegramUpdate;
use App\Services\TelegramBotService;
use App\Support\JobSettings;
use App\Support\Phone;
use App\Support\RuntimeEnv;
use App\Support\SecretBox;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;

const BOT_TOKEN = '123456789:TESTTESTTESTTESTTESTTESTTESTTESTTEST';

/** What a new deploy does on this host: the same database, another APP_KEY. */
function deployWithAnotherAppKey(): void
{
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');
    PlatformSetting::flushValueCache();
}

/** The database's password, as on the host; SQLite has none under test. */
function withDatabasePassword(): void
{
    config(['database.connections.'.config('database.default').'.password' => 'the-password-of-the-managed-database']);
}

/** Only the stored token counts: phpunit.xml also gives one through the environment, which would hide a lost one. */
function storedTokenOnly(): void
{
    config(['services.telegram.bot_token' => '']);
    putenv('TELEGRAM_BOT_TOKEN');
}

afterEach(function (): void {
    putenv('TELEGRAM_BOT_TOKEN=1234567:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdef');
});

/** What Telegram's getMe answers for the fake token. */
function fakeBot(): void
{
    TelegramFake::$results['getMe'] = ['id' => 123456789, 'username' => 'HabeshaDubaiBot'];
}

test('the bot token, and every other saved secret, survives a deploy that changes APP_KEY', function (): void {
    storedTokenOnly();
    withDatabasePassword();
    fakeBot();
    $telegram = app(TelegramBotService::class);
    $telegram->configureToken(BOT_TOKEN);
    PlatformSetting::put('tick_token', RuntimeEnv::conceal('a-scheduler-token-that-is-long-enough'));
    JobSettings::setAnthropicKey('sk-ant-test-key');
    $secret = $telegram->webhookSecret();

    deployWithAnotherAppKey();

    expect($telegram->tokenIsUnreadable())->toBeFalse()
        ->and($telegram->webhookSecret())->toBe($secret)
        ->and(RuntimeEnv::get(null, 'tick_token', null))->toBe('a-scheduler-token-that-is-long-enough')
        ->and(JobSettings::anthropicKey())->toBe('sk-ant-test-key');

    // The delivery Telegram makes right after the deploy is answered, not refused.
    $this->postJson('/api/telegram/webhook/'.$secret, ['update_id' => 0])->assertOk();
});

test('the same phone hashes the same after a deploy, so one account per phone still holds', function (): void {
    withDatabasePassword();
    $hash = Phone::hash('971501234567');

    deployWithAnotherAppKey();

    expect(Phone::hash('971501234567'))->toBe($hash);
});

test('a second account cannot take a phone that was verified before a deploy', function (): void {
    withDatabasePassword();
    joinMember(51, '+971501112233');

    deployWithAnotherAppKey();

    say(52, '/start');
    say(52, 'English');
    shareContact(52, '+971501112233');

    expect(lastTo(52)['text'])->toContain('already verified on another Telegram account')
        ->and(Member::findByTelegramUser(52))->toBeNull();
});

test('nothing is kept in clear: not the token, not the secrets', function (): void {
    storedTokenOnly();
    fakeBot();
    app(TelegramBotService::class)->configureToken(BOT_TOKEN);
    JobSettings::setAnthropicKey('sk-ant-test-key');

    expect((string) PlatformSetting::valueFor(TelegramBotService::STORED_TOKEN_KEY))->not->toContain(BOT_TOKEN)->toStartWith('box1:')
        ->and((string) PlatformSetting::valueFor(JobSettings::ANTHROPIC_KEY))->not->toContain('sk-ant-test-key')
        ->and(SecretBox::open('box1:tampered'))->toBeNull();
});

test('a secret from before is read through the key it was saved under, and moved by the migration', function (): void {
    storedTokenOnly();
    withDatabasePassword();
    PlatformSetting::put(TelegramBotService::STORED_TOKEN_KEY, Crypt::encryptString(BOT_TOKEN));
    PlatformSetting::put('site_api_key', 'enc:'.Crypt::encryptString('a-website-key-that-is-long-enough-1234'));
    PlatformSetting::put('site_url', 'https://habesha.example');

    expect(app(TelegramBotService::class)->configured())->toBeTrue();

    (require database_path('migrations/2026_11_07_000077_reseal_saved_secrets.php'))->up();
    PlatformSetting::flushValueCache();

    expect((string) PlatformSetting::valueFor(TelegramBotService::STORED_TOKEN_KEY))->toStartWith('box1:')
        ->and((string) PlatformSetting::valueFor('site_api_key'))->toStartWith('enc:box1:')
        ->and((string) PlatformSetting::valueFor('site_url'))->toBe('https://habesha.example');

    deployWithAnotherAppKey();

    expect(app(TelegramBotService::class)->configured())->toBeTrue()
        ->and(RuntimeEnv::get(null, 'site_api_key', null))->toBe('a-website-key-that-is-long-enough-1234');
});

test('a token that can no longer be read answers 503 and says so, instead of telling Telegram its secret is wrong', function (): void {
    storedTokenOnly();
    $telegram = app(TelegramBotService::class);
    PlatformSetting::put(TelegramBotService::STORED_TOKEN_KEY, Crypt::encryptString(BOT_TOKEN));
    PlatformSetting::put('tick_token', 'enc:'.Crypt::encryptString('a-scheduler-token-that-is-long-enough'));
    PlatformSetting::put('public_url', 'https://api.habesha.example');

    deployWithAnotherAppKey();

    expect($telegram->tokenIsUnreadable())->toBeTrue()->and($telegram->configured())->toBeFalse();

    $this->postJson('/api/telegram/webhook/anything-at-all-long-enough', ['update_id' => 1])->assertStatus(503);

    Sanctum::actingAs(admin(), ['admin']);
    $settings = $this->getJson('/api/admin/settings')->assertOk()->json('telegram');
    expect($settings['token_unreadable'])->toBeTrue();

    $items = collect($this->getJson('/api/admin/readiness')->assertOk()->json('items'))->keyBy('key');
    expect($items['bot_token']['ok'])->toBeFalse()
        ->and($items['bot_token']['detail'])->toContain('can no longer be read')
        ->and($items['tick_token']['detail'])->toContain('can no longer be read');
});

test('a past delivery error is marked recovered once a message has arrived after it', function (): void {
    storedTokenOnly();
    fakeBot();
    $telegram = app(TelegramBotService::class);
    $telegram->configureToken(BOT_TOKEN);
    PlatformSetting::put('public_url', 'https://api.habesha.example');
    $this->travelTo(now()->startOfMinute());

    TelegramFake::$results['getWebhookInfo'] = [
        'url' => 'https://api.habesha.example/api/telegram/webhook/'.$telegram->webhookSecret(),
        'pending_update_count' => 0,
        'last_error_date' => now()->subMinutes(2)->timestamp,
        'last_error_message' => 'Wrong response from the webhook: 403 Forbidden',
    ];
    Sanctum::actingAs(admin(), ['admin']);
    $item = fn () => collect($this->getJson('/api/admin/readiness')->assertOk()->json('items'))->firstWhere('key', 'webhook');

    expect($this->getJson('/api/admin/settings')->json('telegram.webhook.last_error_recovered'))->toBeFalse()
        ->and($item()['ok'])->toBeFalse();

    TelegramUpdate::query()->create(['update_id' => nextUpdateId(), 'payload' => ['update_id' => 1], 'received_at' => now()->subMinute()]);
    \Illuminate\Support\Facades\Cache::flush();

    expect($this->getJson('/api/admin/settings')->json('telegram.webhook.last_error_recovered'))->toBeTrue()
        ->and($item()['ok'])->toBeTrue();
});
