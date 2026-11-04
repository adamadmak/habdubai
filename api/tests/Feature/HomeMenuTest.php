<?php

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;

/** Every sendPhoto the bot made: where it went, what picture, the caption and the buttons. */
function sentPhotos(): array
{
    $sent = [];

    Http::recorded(function ($request) use (&$sent): void {
        if (str_ends_with($request->url(), '/sendPhoto')) {
            $data = $request->data();
            $sent[] = ['chat_id' => $data['chat_id'] ?? null, 'photo' => $data['photo'] ?? null, 'caption' => (string) ($data['caption'] ?? ''), 'reply_markup' => $data['reply_markup'] ?? null];
        }
    });

    return $sent;
}

/** The labels on a reply keyboard, row by row. */
function keyboardRows(?array $markup): array
{
    return collect($markup['keyboard'] ?? [])->map(fn (array $row) => array_column($row, 'text'))->all();
}

test('joining ends with the welcome card from the website, the links as buttons, and the menu', function (): void {
    PlatformSetting::put('site_url', 'https://habesha.example');
    PlatformSetting::put('telegram_channel_link', 'https://t.me/habesha_dubai');
    PlatformSetting::put('telegram_goods_group_link', 'https://t.me/+goods');

    joinMember(31);

    $photos = sentPhotos();
    $card = end($photos);
    expect($card['chat_id'])->toBe(31)
        ->and($card['photo'])->toBe('https://habesha.example/api/card/en')
        ->and($card['caption'])->toContain('You are in, Abebe K')->not->toContain('not set yet')
        ->and(buttonsOf($card))->toBe(['https://habesha.example/en', 'https://habesha.example/en/account', 'https://t.me/habesha_dubai', 'https://t.me/+goods']);

    $menu = lastMessage();
    expect($menu['text'])->toContain('menu at the bottom')
        ->and($menu['reply_markup']['is_persistent'])->toBeTrue()
        ->and(keyboardRows($menu['reply_markup']))->toBe([['💼 Jobs', '📦 Goods', '❓ Ask'], ['📍 Places', '🆘 Safety', '👤 Me']]);
});

test('without a website address the welcome is plain text and says the links are not set yet', function (): void {
    joinMember(32);

    $texts = textsTo(32);
    expect(sentPhotos())->toBe([])
        ->and($texts[count($texts) - 2])->toContain('You are in, Abebe K')->toContain('not set yet')
        ->and(keyboardRows(lastMessage()['reply_markup']))->toHaveCount(2);
});

test('when Telegram cannot fetch the card the welcome still arrives as text, with its buttons', function (): void {
    PlatformSetting::put('site_url', 'https://habesha.example');
    PlatformSetting::put('telegram_channel_link', 'https://t.me/habesha_dubai');
    TelegramFake::$errors['sendPhoto'] = [400, 'Bad Request: wrong file identifier/HTTP URL specified'];

    joinMember(33);

    $welcome = collect(sentMessages())->first(fn (array $m) => str_contains($m['text'], 'You are in'));
    expect($welcome)->not->toBeNull()
        ->and(buttonsOf($welcome))->toContain('https://t.me/habesha_dubai')
        ->and(keyboardRows(lastMessage()['reply_markup']))->toHaveCount(2);
});

test('a menu button does exactly what its command does, whatever language its label is in', function (): void {
    joinMember(34);

    say(34, '/directory');
    $byCommand = lastText();
    say(34, '📍 ቦታዎች');
    expect(lastText())->toBe($byCommand)->toContain('Pick a city');

    say(34, '👤 Me');
    expect(lastText())->toContain('Verified since')->toContain('Abebe K');

    say(34, '🆘 Nageenya');
    expect(lastText())->toContain('Help and safety');
});

test('/start for a member, a change of language and a cancelled flow all bring the menu back', function (): void {
    joinMember(35);

    say(35, '/start');
    $sent = sentMessages();
    $hello = $sent[count($sent) - 2];
    expect($hello['text'])->toContain('Welcome back, Abebe K')
        ->and($hello['reply_markup']['is_persistent'])->toBeTrue();

    say(35, '/language');
    say(35, 'አማርኛ');
    expect(keyboardRows(lastMessage()['reply_markup']))->toBe([['💼 ሥራ', '📦 ዕቃ', '❓ ጠይቅ'], ['📍 ቦታዎች', '🆘 ደህንነት', '👤 እኔ']]);

    say(35, '/ask');
    say(35, '/cancel');
    expect(keyboardRows(lastMessage()['reply_markup']))->toHaveCount(2);
});

test('someone who left gets no menu back on cancel', function (): void {
    joinMember(36);
    say(36, '/leave');
    say(36, 'Yes, leave');

    say(36, '/cancel');
    expect(lastMessage()['reply_markup'])->toBe(['remove_keyboard' => true]);
});

/** The chat's state as the bot holds it. */
function stateOf(int $userId): string
{
    return (string) \App\Models\Conversation::query()->where('telegram_user_id', $userId)->value('state');
}

test('a command stops a flow left half-way, says so, and takes the flow\'s buttons off the screen', function (): void {
    joinMember(37);

    say(37, '/addbusiness');
    expect(stateOf(37))->toBe('biz_name');

    say(37, '/help');
    $sent = sentMessages();
    expect(stateOf(37))->toBe('idle')
        ->and($sent[count($sent) - 2]['text'])->toContain('I stopped what you were doing')
        ->and(keyboardRows($sent[count($sent) - 2]['reply_markup']))->toHaveCount(2)
        ->and(lastText())->toContain('Commands:');

    // What the member writes next is a question for the assistant, not the business's name.
    say(37, 'Abyssinia Spices');
    expect(\App\Models\Business::query()->count())->toBe(0);
});

test('a menu button stops a flow too, and does what it says', function (): void {
    joinMember(38);

    say(38, '/directory');
    $directory = lastText();

    say(38, '/addbusiness');
    say(38, '📍 Places');
    expect(stateOf(38))->toBe('idle')->and(lastText())->toBe($directory);
});

test('a website sign-in does not disturb a flow; /cancel still says cancelled and nothing else', function (): void {
    PlatformSetting::put('telegram_bot_username', 'HabeshaDubaiBot');
    joinMember(39);
    say(39, '/addbusiness');

    $start = test()->postJson('/api/owner/login/start')->assertOk()->json();
    say(39, '/start login_'.$start['ref']);
    expect(stateOf(39))->toBe('biz_name')->and(lastText())->toContain('Sign in to the HabUAE website');

    $before = count(sentMessages());
    say(39, '/cancel');
    expect(stateOf(39))->toBe('idle')->and(count(sentMessages()))->toBe($before + 1)->and(lastText())->toBe('Cancelled.');
});

test('a slash word the bot does not know is an answer inside a flow and a typo outside one', function (): void {
    joinMember(40);

    say(40, '/addbusiness');
    say(40, '/ 24 Hour Cafe');
    expect(stateOf(40))->not->toBe('idle');

    say(40, '/cancel');
    say(40, '/wrongcommand');
    expect(lastText())->toContain('I did not understand that')
        ->and(\App\Models\AssistantMessage::query()->count())->toBe(0);
});

test('someone who left stays left until they have agreed to the rules again', function (): void {
    $member = joinMember(41);
    say(41, '/leave');
    say(41, 'Yes, leave');

    say(41, '/start');
    say(41, 'English');
    say(41, 'Abebe K');
    expect($member->fresh()->hasLeft())->toBeTrue()->and($member->fresh()->isActive())->toBeFalse();

    say(41, 'Dubai');
    say(41, 'Driver');
    say(41, '✅ Done');
    expect($member->fresh()->hasLeft())->toBeTrue();

    say(41, '✅ I agree');
    expect($member->fresh()->hasLeft())->toBeFalse()->and($member->fresh()->isActive())->toBeTrue()
        ->and(substr_count(implode("\n", textsTo(41)), 'You are in, Abebe K'))->toBe(2);
});

test('"Interested" on a post that no longer exists gets a plain answer, not a failed update', function (): void {
    joinMember(42);

    say(42, '/start g999999')->assertOk();

    expect(lastText())->toBe(app(\App\Bot\BotMessages::class)->text('goods_post_closed', 'en'))
        ->and(\App\Models\TelegramUpdate::query()->whereNotNull('error')->count())->toBe(0);
});

test('a phone number is a phone number; a sum of money is not', function (string $text, bool $phone): void {
    expect(\App\Bot\GroupModerator::hasPhoneNumber($text))->toBe($phone);
})->with([
    ['Paid 1 200 000 birr', false],
    ['The flight is 2026 11 20', false],
    ['Rent is 2500 a month', false],
    ['call 050 123 4567', true],
    ['+971 50 123 4567', true],
    ['0501234567', true],
]);
