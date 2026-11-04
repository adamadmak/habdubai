<?php

use App\Models\Member;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;

test('a returning member gets welcome back, not the join flow', function (): void {
    joinMember(30);

    say(30, '/start');

    expect(implode("\n", textsTo(30)))->toContain('Welcome back, Abebe K')
        ->and(Member::query()->count())->toBe(1);
});

test('/profile shows the member card in their language', function (): void {
    joinMember(30);

    say(30, '/profile');

    expect(lastText())->toContain('Abebe K')->toContain('Dubai')->toContain('Driver')->toContain('Helper points: 0');
});

test('/language switches the member and later replies', function (): void {
    joinMember(30);

    say(30, '/language');
    say(30, 'ትግርኛ');

    expect(Member::findByTelegramUser(30)->language)->toBe('ti');

    say(30, '/help');
    expect(lastText())->toContain('ትእዛዛት');
});

test('/leave asks, then hides the member and kicks them from the groups', function (): void {
    PlatformSetting::put('telegram_questions_group_id', '-1001');
    joinMember(30);

    say(30, '/leave');
    expect(lastText())->toContain('Leave the community?');

    say(30, 'Yes, leave');

    $member = Member::findByTelegramUser(30);
    expect($member->left_at)->not->toBeNull()
        ->and($member->isActive())->toBeFalse()
        ->and(lastText())->toContain('You have left');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/banChatMember') && $request['chat_id'] === '-1001' && $request['user_id'] === 30);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/unbanChatMember'));
});

test('a banned member gets only the ban message', function (): void {
    $member = joinMember(30);
    $member->forceFill(['banned_at' => now()])->save();

    say(30, '/start');
    expect(lastText())->toContain('banned');

    say(30, '/profile');
    expect(lastText())->toContain('banned');
});

test('a stranger sending anything but /start is pointed to /start, in the language Telegram hints', function (): void {
    say(31, 'hello');
    expect(lastText())->toContain('/start')->toContain('ገና አባል');

    say(32, 'hello', ['language_code' => 'en']);
    expect(lastText())->toContain('Send /start');

    say(33, 'hello', ['language_code' => 'ar']);
    expect(lastText())->toContain('Send /start');
});

test('/cancel ends a leave confirmation', function (): void {
    joinMember(30);

    say(30, '/leave');
    say(30, '❌ Cancel');

    expect(Member::findByTelegramUser(30)->left_at)->toBeNull()
        ->and(lastText())->toContain('Cancelled');
});
