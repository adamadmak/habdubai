<?php

use App\Models\Conversation;
use App\Models\Member;
use App\Models\PlatformSetting;

beforeEach(function (): void {
    say(20, '/start');
    say(20, 'English');
});

test('a forwarded contact card that is not the sender\'s is refused', function (): void {
    shareContact(20, '+971501234567', contactUserId: 999);

    expect(Member::query()->count())->toBe(0)
        ->and(lastText())->toContain('belongs to someone else')
        ->and(lastMessage()['reply_markup']['keyboard'][0][0]['request_contact'])->toBeTrue()
        ->and(Conversation::query()->where('telegram_user_id', 20)->value('state'))->toBe('phone');
});

test('a typed number is not accepted in place of the card', function (): void {
    say(20, '+971501234567');

    expect(Member::query()->count())->toBe(0)
        ->and(lastMessage()['reply_markup']['keyboard'][0][0]['request_contact'])->toBeTrue();
});

test('an unreadable number asks again', function (): void {
    shareContact(20, 'abc');

    expect(Member::query()->count())->toBe(0)
        ->and(lastText())->toContain('could not be read');
});

test('the same phone from a second Telegram account is refused and the first account is told', function (): void {
    PlatformSetting::put('support_telegram_username', 'habesha_admin');
    joinMember(21, '+971501234567');

    shareContact(20, '+971501234567');

    $sent = sentMessages();
    $toSecond = array_values(array_filter($sent, fn ($m) => (int) $m['chat_id'] === 20));
    $toFirst = array_values(array_filter($sent, fn ($m) => (int) $m['chat_id'] === 21));

    expect(Member::query()->count())->toBe(1)
        ->and(end($toSecond)['text'])->toContain('already verified')->toContain('@habesha_admin')
        ->and(end($toFirst)['text'])->toContain('Someone tried to verify your phone number')
        ->and(Conversation::query()->where('telegram_user_id', 20)->value('state'))->toBe('idle');
});

test('the phone is stored only as a hash and the last four digits', function (): void {
    shareContact(20, '+971 50 123 4567');

    $member = Member::findByTelegramUser(20);

    expect($member->phone_hash)->toBe(phoneHash('971501234567'))
        ->and($member->phone_last4)->toBe('4567')
        ->and($member->getAttributes())->not->toHaveKey('phone');
});

test('a number outside the allowed countries is accepted but marked for review', function (): void {
    shareContact(20, '+447700900123');

    $member = Member::findByTelegramUser(20);

    expect($member->phone_country)->toBe('44')
        ->and($member->needs_review)->toBeTrue()
        ->and(implode(' ', array_column(sentMessages(), 'text')))->toContain('outside the UAE and Ethiopia');
});

test('the allowed countries can be widened from settings', function (): void {
    PlatformSetting::put('allowed_phone_countries', '971, 251, 44');

    shareContact(20, '+447700900123');

    expect(Member::findByTelegramUser(20)->needs_review)->toBeFalse();
});

test('a member who shares a new number from the same account keeps their record', function (): void {
    shareContact(20, '+971501234567');
    $id = Member::findByTelegramUser(20)->id;

    // Starting over from the language step goes straight to the name, the
    // number being proven already; the record is the same one.
    say(20, '/cancel');
    say(20, '/start');
    say(20, 'English');

    expect(lastText())->toContain('What name')
        ->and(Member::findByTelegramUser(20)->id)->toBe($id);
});
