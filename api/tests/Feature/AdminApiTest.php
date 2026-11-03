<?php

use App\Models\AuditLog;
use App\Models\BotText;
use App\Models\Member;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

test('every admin route denies a guest', function (string $method, string $path): void {
    $this->json($method, $path)->assertStatus(401);
})->with([
    ['GET', '/api/admin/me'],
    ['GET', '/api/admin/dashboard'],
    ['GET', '/api/admin/members'],
    ['GET', '/api/admin/bot-texts'],
    ['GET', '/api/admin/settings'],
    ['PUT', '/api/admin/settings'],
    ['GET', '/api/admin/audit'],
]);

test('login refuses wrong credentials and issues a token for the right ones', function (): void {
    admin();

    $this->postJson('/api/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertStatus(422);

    $response = $this->postJson('/api/admin/login', ['email' => 'admin@example.com', 'password' => 'correct-horse-battery'])->assertOk();
    $token = $response->json('token');

    $this->withToken($token)->getJson('/api/admin/me')->assertOk()->assertJsonPath('user.email', 'admin@example.com');
    $this->withToken($token)->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('members.total', 0);

    $this->withToken($token)->postJson('/api/admin/logout')->assertOk();

    expect(\Laravel\Sanctum\PersonalAccessToken::query()->count())->toBe(0);
    $this->app['auth']->forgetGuards(); // the test kernel keeps the guard's resolved user between requests
    $this->withToken($token)->getJson('/api/admin/me')->assertStatus(401);
});

test('members can be listed, searched, reviewed and banned', function (): void {
    joinMember(40, '+971501234567');
    joinMember(41, '+447700900123');
    Sanctum::actingAs(admin(), ['admin']);

    $this->getJson('/api/admin/members')->assertOk()->assertJsonPath('meta.total', 2);
    $this->getJson('/api/admin/members?status=needs_review')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.phone_country', '44');
    $this->getJson('/api/admin/members?search=4567')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.telegram_user_id', '40');

    $reviewed = Member::findByTelegramUser(41);
    $this->patchJson('/api/admin/members/'.$reviewed->id, ['needs_review' => false, 'role' => 'leader'])->assertOk()
        ->assertJsonPath('member.role', 'leader')->assertJsonPath('member.needs_review', false);

    $this->postJson('/api/admin/members/'.$reviewed->id.'/ban', ['reason' => 'spam'])->assertOk()->assertJsonPath('member.status', 'banned');
    say(41, '/profile');
    expect(lastText())->toContain('banned');

    $this->postJson('/api/admin/members/'.$reviewed->id.'/unban')->assertOk()->assertJsonPath('member.status', 'active');

    expect(AuditLog::query()->where('subject_id', $reviewed->id)->pluck('action')->all())
        ->toContain('member.updated', 'member.banned', 'member.unbanned');

    $this->getJson('/api/admin/members/'.$reviewed->id)->assertOk()->assertJsonPath('member.trades.0', 'driver');
});

test('an edited bot text changes what the bot says, and a blank resets it', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    joinMember(42);

    $this->putJson('/api/admin/bot-texts/help', ['language' => 'en', 'text' => 'Custom help text'])->assertOk();
    say(42, '/help');
    expect(lastText())->toBe('Custom help text');

    $this->putJson('/api/admin/bot-texts/help', ['language' => 'en', 'text' => ''])->assertOk();
    expect(BotText::query()->count())->toBe(0);
    say(42, '/help');
    expect(lastText())->toContain('Commands:');

    $this->putJson('/api/admin/bot-texts/no_such_key', ['language' => 'en', 'text' => 'x'])->assertStatus(404);
    $this->getJson('/api/admin/bot-texts')->assertOk()->assertJsonPath('data.0.key', 'welcome');
});

test('settings are saved and read back, the token is verified and never returned', function (): void {
    Sanctum::actingAs(admin(), ['admin']);

    $this->putJson('/api/admin/settings', [
        'public_url' => 'https://bot.example.com',
        'support_telegram_username' => '@habesha_admin',
        'allowed_phone_countries' => '971,251',
        'telegram_channel_link' => 'https://t.me/habesha_dubai',
    ])->assertOk()->assertJsonPath('settings.public_url', 'https://bot.example.com');

    expect(PlatformSetting::valueFor('telegram_channel_link'))->toBe('https://t.me/habesha_dubai');

    $this->postJson('/api/admin/settings/telegram', ['token' => 'short'])->assertStatus(422);

    $response = $this->postJson('/api/admin/settings/telegram', ['token' => '7654321:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefgh'])->assertOk();
    expect($response->json('bot.username'))->toBe('HabeshaDubaiBot')
        ->and(json_encode($response->json()))->not->toContain('7654321:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefgh')
        ->and(PlatformSetting::valueFor('telegram_bot_token_encrypted'))->not->toBeNull();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/setWebhook') && str_starts_with($request['url'], 'https://bot.example.com/api/telegram/webhook/'));

    $this->getJson('/api/admin/settings')->assertOk()->assertJsonPath('telegram.username', 'HabeshaDubaiBot');
});

test('on a local machine the token is saved and the missing webhook is reported, not treated as a failure', function (): void {
    Sanctum::actingAs(admin(), ['admin']);

    $this->postJson('/api/admin/settings/telegram', ['token' => '7654321:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefgh'])
        ->assertOk()
        ->assertJsonPath('webhook', null)
        ->assertJsonPath('webhook_error', 'Set the public URL first; Telegram cannot reach localhost.');

    expect(app(\App\Services\TelegramBotService::class)->configured())->toBeTrue();
});

test('the webhook cannot be registered against localhost', function (): void {
    Sanctum::actingAs(admin(), ['admin']);

    $this->postJson('/api/admin/settings/webhook')->assertStatus(422);
});
