<?php

use App\Bot\BotMessages;
use App\Bot\Choices;
use App\Models\Conversation;
use App\Models\Member;
use App\Support\TelegramHtml;

// Afaan Oromoo, the bot's fourth language: every text, every fixed list, the join flow and button matching.

/** @return array<int, string> the {placeholders} in a text, sorted */
function omPlaceholders(string $text): array
{
    preg_match_all('/\{[a-z_]+\}/', $text, $m);
    $found = $m[0];
    sort($found);

    return $found;
}

test('every bot text has an Oromo version with the English placeholders that Telegram accepts', function (): void {
    $problems = [];

    foreach (BotMessages::defaults() as $key => $languages) {
        $om = $languages['om'] ?? '';

        if (trim($om) === '') {
            $problems[] = "$key: no om text";

            continue;
        }
        if (omPlaceholders($om) !== omPlaceholders($languages['en'])) {
            $problems[] = "$key: placeholders differ from English";
        }
        if ($html = TelegramHtml::problem($om)) {
            $problems[] = "$key: $html";
        }
    }

    expect($problems)->toBe([])
        ->and(count(BotMessages::defaults()))->toBeGreaterThan(300);
});

test('every fixed list in Choices has an Oromo label that keeps the English emoji', function (): void {
    $lists = array_filter(
        (new ReflectionClass(Choices::class))->getConstants(),
        fn ($value) => is_array($value) && is_array(reset($value)) && isset(reset($value)['en'])
    );
    $problems = [];

    foreach ($lists as $name => $list) {
        foreach ($list as $key => $labels) {
            $om = $labels['om'] ?? '';
            preg_match('/^[^\p{L}]*/u', $labels['en'], $en);
            preg_match('/^[^\p{L}]*/u', $om, $omPrefix);

            if (trim($om) === '' || $omPrefix[0] === $om) {
                $problems[] = "$name.$key: no om label";
            } elseif ($omPrefix[0] !== $en[0]) {
                $problems[] = "$name.$key: leading emoji differs from English";
            }
        }
    }

    expect($problems)->toBe([])
        ->and(count($lists))->toBeGreaterThanOrEqual(10)
        ->and(Choices::LANGUAGES)->toContain('om')
        ->and(Choices::LANGUAGE_LABELS['om'])->toBe('Afaan Oromoo')
        ->and(Choices::keyFromLabel(Choices::GOODS_CITIES_ET, 'Finfinnee'))->toBe('addis_ababa');
});

test('a new member joins in Afaan Oromoo and the bot answers in Oromo', function (): void {
    say(40, '/start');
    say(40, 'Afaan Oromoo');

    expect(Conversation::query()->where('telegram_user_id', 40)->value('language'))->toBe('om')
        ->and(textsTo(40))->toContain('Afaan gara Afaan Oromootti jijjiirameera.')
        ->and(lastText())->toContain('lakkoofsa bilbilaa')
        ->and(lastMessage()['reply_markup']['keyboard'][0][0]['text'])->toBe('📱 Lakkoofsa bilbila koo qoodi');

    shareContact(40, '+971501234570');
    expect(Member::findByTelegramUser(40)->language)->toBe('om')
        ->and(lastText())->toContain('Miseensonni biroo maqaa kamiin');

    say(40, 'Caalaa Tolasaa');
    expect(lastText())->toBe('Eessa jiraattu?')
        ->and(collect(lastMessage()['reply_markup']['keyboard'])->flatten(1)->pluck('text'))->toContain('Imaaraa biraa');

    say(40, 'Sharjah');
    expect(lastText())->toContain('Hojii akkamii')
        ->and(collect(lastMessage()['reply_markup']['keyboard'])->flatten(1)->pluck('text'))->toContain('Konkolaachisaa')->toContain('✅ Xumureera');

    say(40, 'Konkolaachisaa');
    expect(lastText())->toContain('Filatame: Konkolaachisaa');

    say(40, '✅ Xumureera');
    expect(lastText())->toContain('Seerota hawaasa kanaa');

    say(40, '✅ Nan waliigala');
    $member = Member::findByTelegramUser(40);
    expect($member->isActive())->toBeTrue()
        ->and($member->language)->toBe('om')
        ->and($member->emirate)->toBe('sharjah')
        ->and($member->tradeKeys())->toBe(['driver'])
        ->and(lastText())->toContain('Baga miseensa taatan, Caalaa Tolasaa');

    say(40, '/help');
    expect(lastText())->toContain('Ajajawwan')->toContain('/language — afaan jijjiiruu');
});

test('/language offers Afaan Oromoo two to a row, and the profile then reads in Oromo', function (): void {
    joinMember(41);

    say(41, '/language');
    expect(lastMessage()['reply_markup']['keyboard'])->toBe([
        [['text' => 'አማርኛ'], ['text' => 'ትግርኛ']],
        [['text' => 'Afaan Oromoo'], ['text' => 'English']],
    ]);

    say(41, 'Afaan Oromoo');
    expect(Member::findByTelegramUser(41)->language)->toBe('om')
        ->and(lastText())->toBe('Afaan gara Afaan Oromootti jijjiirameera.');

    say(41, '/profile');
    expect(lastText())->toContain('🌐 Afaan Oromoo')->toContain('Qabxii gargaarsaa: 0')->toContain('Konkolaachisaa');
});

test('pressed() recognises an Oromo button, and no Oromo button reads like another one', function (): void {
    $messages = app(BotMessages::class);

    expect($messages->pressed('btn_done', '✅ Xumureera'))->toBeTrue()
        ->and($messages->pressed('btn_skip', ' ⏭ Darbi '))->toBeTrue()
        ->and($messages->pressed('btn_keep_name', "Caalaa haa ta'u", ['name' => 'Caalaa']))->toBeTrue()
        ->and($messages->pressed('btn_agree', '✅ Xumureera'))->toBeFalse();

    // Pressed labels are matched across every language, so an Oromo label that
    // equals another button's label in any language would press both.
    $buttons = array_filter(BotMessages::defaults(), fn (string $key) => str_starts_with($key, 'btn_'), ARRAY_FILTER_USE_KEY);
    $choiceLabels = collect((new ReflectionClass(Choices::class))->getConstants())
        ->filter(fn ($value) => is_array($value) && is_array(reset($value)) && isset(reset($value)['en']))
        ->flatten()->all();
    $clashes = [];

    foreach ($buttons as $key => $labels) {
        foreach ($buttons as $other => $otherLabels) {
            if ($other !== $key && $labels['en'] !== $otherLabels['en'] && in_array($labels['om'], $otherLabels, true)) {
                $clashes[] = "$key = $other";
            }
        }
        if (in_array($labels['om'], $choiceLabels, true)) {
            $clashes[] = "$key = a Choices label";
        }
    }

    expect($clashes)->toBe([]);
});
