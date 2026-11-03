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
        ->and(buttonsOf($card))->toBe(['https://t.me/habesha_dubai', 'https://t.me/+goods', 'https://habesha.example/en', 'https://habesha.example/en/account']);

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
    expect(lastText())->toContain('Welcome back, Abebe K')
        ->and(lastMessage()['reply_markup']['is_persistent'])->toBeTrue();

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

test('a command interrupts a flow, so the next message is never taken as the flow\'s answer', function (): void {
    joinMember(37);

    say(37, '/ask');
    say(37, '/start');
    expect(\App\Models\Conversation::query()->where('telegram_user_id', 37)->value('state'))->toBe('idle');

    say(37, '/jobs');
    $jobs = lastText();
    say(37, '/ask');
    say(37, '💼 Jobs');
    expect(lastText())->toBe($jobs);
});

test('"Interested" on a post that no longer exists gets a plain answer, not a failed update', function (): void {
    joinMember(38);

    say(38, '/start g999999')->assertOk();

    expect(lastText())->toBe(app(\App\Bot\BotMessages::class)->text('goods_post_closed', 'en'))
        ->and(\App\Models\TelegramUpdate::query()->where('status', 'failed')->count())->toBe(0);
});
