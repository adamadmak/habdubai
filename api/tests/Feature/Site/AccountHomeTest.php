<?php

use App\Models\JobFilter;
use App\Models\OwnerLoginRequest;
use App\Models\PlatformSetting;
use App\Models\Question;

/** "Continue with Telegram" from the website for this Telegram user: the owner token. */
function homeToken(int $userId): string
{
    $start = test()->postJson('/api/owner/login/start')->assertOk()->json();
    say($userId, '/start login_'.$start['ref'], ['language_code' => 'en']);

    test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'callback_query' => [
            'id' => 'cb'.nextUpdateId(),
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'X', 'language_code' => 'en'],
            'message' => ['message_id' => 77, 'chat' => ['id' => $userId, 'type' => 'private']],
            'data' => 'lg:ok:'.OwnerLoginRequest::query()->where('ref', $start['ref'])->value('id'),
        ],
    ])->assertOk();
    preg_match('/<b>(\d{6})<\/b>/', lastTo($userId)['text'], $code);

    return test()->postJson('/api/owner/login/verify', ['ref' => $start['ref'], 'secret' => $start['secret'], 'code' => $code[1]])->assertOk()->json('token');
}

function asHome(string $token)
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

beforeEach(function (): void {
    // "Continue with Telegram" needs a bot to open; the website address makes the links.
    PlatformSetting::put('telegram_bot_username', 'HabeshaDubaiBot');
    PlatformSetting::put('site_url', 'https://habesha.example');
});

test('the home page carries who the member is and what they have going on', function (): void {
    PlatformSetting::put('telegram_channel_link', 'https://t.me/habesha_dubai');
    $member = joinMember(41);
    $token = homeToken(41);

    asHome($token)->postJson('/api/owner/goods', [
        'kind' => 'traveller', 'direction' => 'to_et', 'date' => now()->addDays(10)->toDateString(),
        'from' => 'dubai', 'to' => 'addis_ababa', 'kilos' => 10, 'categories' => ['clothes', 'documents'],
    ])->assertCreated();
    Question::query()->create(['member_id' => $member->id, 'category' => 'visa', 'title' => 'Can I change employer?', 'body' => 'Still on probation.', 'status' => 'open', 'last_activity_at' => now()]);
    JobFilter::query()->create(['member_id' => $member->id, 'alerts_on' => true]);

    $home = asHome($token)->getJson('/api/owner/home')->assertOk()->json();

    expect($home['owner']['member'])->toBeTrue()
        ->and($home['member']['name'])->toBe('Abebe K')
        ->and($home['member']['emirate'])->toBe('dubai')
        ->and($home['member']['trades'])->toBe(['driver'])
        ->and($home['member']['language'])->toBe('en')
        ->and($home['member']['verified_since'])->not->toBeNull()
        ->and($home['member']['helper_points'])->toBe(0)
        ->and($home['member']['rank'])->toBeNull()
        ->and($home['goods'])->toMatchArray(['open_posts' => 1, 'live_deals' => 0, 'completed' => 0])
        ->and($home['questions']['total'])->toBe(1)
        ->and($home['questions']['open'])->toBe(1)
        ->and($home['questions']['latest'][0]['title'])->toBe('Can I change employer?')
        ->and($home['jobs']['alerts_on'])->toBeTrue()
        ->and($home['jobs']['saved'])->toBe([])
        ->and($home['businesses'])->toBe(['approved' => 0, 'pending' => 0, 'claims_pending' => 0])
        ->and($home['links']['channel'])->toBe('https://t.me/habesha_dubai')
        ->and($home['links']['bot'])->toContain('t.me/');
});

test('an account that is not a member gets the listings part and the bot link only', function (): void {
    $home = asHome(homeToken(42))->getJson('/api/owner/home')->assertOk()->json();

    expect($home['owner']['member'])->toBeFalse()
        ->and($home['member'])->toBeNull()
        ->and($home['goods'])->toBeNull()
        ->and($home['questions'])->toBeNull()
        ->and($home['jobs'])->toBeNull()
        ->and($home['businesses']['approved'])->toBe(0)
        ->and($home['links']['bot'])->toContain('t.me/');
});

test('the home page needs an owner token', function (): void {
    test()->getJson('/api/owner/home')->assertUnauthorized();
});

test('a banned member cannot use an owner account made before they joined', function (): void {
    $token = homeToken(43);
    expect(\App\Models\BusinessOwner::query()->where('telegram_user_id', 43)->value('member_id'))->toBeNull();

    $member = joinMember(43);
    // The first owner call after joining links the two.
    asHome($token)->getJson('/api/owner/home')->assertOk()->assertJsonPath('owner.member', true);

    $member->forceFill(['banned_at' => now(), 'ban_reason' => 'scam'])->save();
    asHome($token)->getJson('/api/owner/home')->assertForbidden();
});

test('page views are counted only with the website key once one is set', function (): void {
    test()->postJson('/api/public/pageview', ['path' => '/en/jobs'])->assertSuccessful();

    PlatformSetting::put('site_api_key', str_repeat('k', 40));
    test()->postJson('/api/public/pageview', ['path' => '/en/jobs'])->assertForbidden();
    test()->withHeader('X-Site-Key', str_repeat('k', 40))->postJson('/api/public/pageview', ['path' => '/en/jobs'])->assertSuccessful();
    test()->flushHeaders();
});
