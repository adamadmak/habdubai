<?php

use App\Models\Conversation;
use App\Models\Member;
use App\Models\PlatformSetting;

test('/start greets in every language and offers the language buttons, two to a row', function (): void {
    say(7, '/start');

    $last = lastMessage();
    expect($last['text'])->toContain('Welcome to Habesha Dubai')->toContain('እንኳን ደህና መጡ')->toContain('እንቋዕ ብደሓን')->toContain('Baga nagaan gara Habesha Dubai dhuftan')
        ->and(collect($last['reply_markup']['keyboard'])->map(fn (array $row) => array_column($row, 'text'))->all())
        ->toBe([['አማርኛ', 'ትግርኛ'], ['Afaan Oromoo', 'English']])
        ->and(Conversation::query()->where('telegram_user_id', 7)->value('state'))->toBe('language');
});

test('a new member joins end to end and is active with their trades', function (): void {
    PlatformSetting::put('telegram_channel_link', 'https://t.me/habesha_dubai');
    PlatformSetting::put('telegram_questions_group_link', 'https://t.me/+questions');

    say(7, '/start');
    say(7, 'አማርኛ');
    expect(lastText())->toContain('ስልክ ቁጥር')
        ->and(lastMessage()['reply_markup']['keyboard'][0][0]['request_contact'])->toBeTrue();

    shareContact(7, '+971501234567');
    $member = Member::findByTelegramUser(7);
    expect($member)->not->toBeNull()
        ->and($member->verified_at)->not->toBeNull()
        ->and($member->agreed_rules_at)->toBeNull()
        ->and($member->phone_last4)->toBe('4567')
        ->and($member->phone_country)->toBe('971')
        ->and($member->needs_review)->toBeFalse()
        ->and($member->language)->toBe('am')
        ->and(lastText())->toContain('በምን ስም');

    say(7, 'Abebe Kebede');
    expect(lastText())->toContain('የት ነው');

    say(7, 'ዱባይ');
    expect(lastText())->toContain('ምን ሥራ');

    say(7, 'ሹፌር');
    say(7, 'ጥበቃ');
    say(7, '✅ ጨርሻለሁ');
    expect(lastText())->toContain('ህጎች');

    say(7, '✅ እስማማለሁ');
    $member->refresh();
    expect($member->agreed_rules_at)->not->toBeNull()
        ->and($member->isActive())->toBeTrue()
        ->and($member->display_name)->toBe('Abebe Kebede')
        ->and($member->emirate)->toBe('dubai')
        ->and($member->tradeKeys())->toBe(['driver', 'security'])
        ->and(lastText())->toContain('Abebe Kebede')->toContain('https://t.me/habesha_dubai')->toContain('https://t.me/+questions')
        ->and(Conversation::query()->where('telegram_user_id', 7)->value('state'))->toBe('idle');
});

test('the Telegram name can be kept with one tap', function (): void {
    say(8, '/start');
    say(8, 'English');
    shareContact(8, '+971501234568');

    expect(lastMessage()['reply_markup']['keyboard'][0][0]['text'])->toBe('Keep Abebe');

    say(8, 'Keep Abebe');

    expect(Member::findByTelegramUser(8)->display_name)->toBe('Abebe')
        ->and(lastText())->toContain('Where do you live');
});

test('a name with a link or a phone number is refused', function (): void {
    say(9, '/start');
    say(9, 'English');
    shareContact(9, '+971501234569');

    say(9, 'call 0501234567');
    expect(lastText())->toContain('2 to 40 letters');

    say(9, 'https://t.me/spam');
    expect(lastText())->toContain('2 to 40 letters');

    say(9, 'Sara');
    expect(lastText())->toContain('Where do you live');
});

test('more than three trades is refused and Done needs at least one', function (): void {
    say(10, '/start');
    say(10, 'English');
    shareContact(10, '+971501234570');
    say(10, 'Sara');
    say(10, 'Sharjah');

    say(10, '✅ Done');
    expect(lastText())->toContain('at least one');

    foreach (['Driver', 'Security', 'Cleaning'] as $trade) {
        say(10, $trade);
    }
    say(10, 'Hospitality');
    expect(lastText())->toContain('Three is the most');

    // Tapping a picked trade again removes it.
    say(10, 'Driver');
    expect(lastText())->toContain('Picked: Security, Cleaning');

    say(10, '✅ Done');
    say(10, '✅ I agree');

    expect(Member::findByTelegramUser(10)->tradeKeys())->toBe(['security', 'cleaning']);
});

test('/start in the middle of joining resumes the current step', function (): void {
    say(11, '/start');
    say(11, 'English');
    shareContact(11, '+971501234571');
    say(11, 'Sara');

    say(11, '/start');
    expect(lastText())->toContain('Where do you live')
        ->and(Member::query()->count())->toBe(1);
});

test('without invite links the done message says an admin will send them', function (): void {
    joinMember(12);

    expect(lastText())->toContain('not set yet');
});
