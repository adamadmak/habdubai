<?php

use App\Support\TelegramHtml;
use App\Support\TelegramText;

test('text that fits is left alone', function (): void {
    $html = "<b>Hello</b>\nሰላም &amp; welcome";

    expect(TelegramText::clip($html))->toBe($html)
        ->and(TelegramText::chunks($html))->toBe([$html]);
});

test('a clip ends at a line break with the open tags closed and a mark where it was cut', function (): void {
    $html = "<blockquote><b>Title</b>\n".str_repeat("A line of text with an <a href=\"https://example.com\">link</a> &amp; more.\n", 40).'</blockquote>';

    $clip = TelegramText::clip($html, 500);

    expect(TelegramText::length($clip))->toBeLessThanOrEqual(500)
        ->and(TelegramHtml::problem($clip))->toBeNull()
        ->and($clip)->toEndWith('…</blockquote>')
        ->and($clip)->not->toContain("\n…");
});

test('parts reopen the tags a cut closed, and together keep every line', function (): void {
    $lines = array_map(fn (int $i) => 'Line '.$i.' — ደህና ነህ? 😀', range(1, 120));
    $html = "<i>\n".implode("\n", $lines)."\n</i>";

    $parts = TelegramText::chunks($html, 600);

    expect(count($parts))->toBeGreaterThan(1);
    foreach ($parts as $part) {
        expect(TelegramText::length($part))->toBeLessThanOrEqual(600)
            ->and(TelegramHtml::problem($part))->toBeNull()
            ->and($part)->toStartWith('<i>');
    }
    $joined = implode("\n", $parts);
    foreach ([1, 60, 120] as $i) {
        expect($joined)->toContain('Line '.$i.' — ');
    }
});

test('an entity is never split, and an emoji counts as two', function (): void {
    expect(TelegramText::length('😀'))->toBe(2)->and(TelegramText::length('ሰ'))->toBe(1);

    $clip = TelegramText::clip(str_repeat('a&amp;', 50), 21);
    expect(TelegramText::length($clip))->toBeLessThanOrEqual(21)
        ->and(preg_match('/&(?![a-z]+;)/', $clip))->toBe(0);
});

test('one line longer than a message is cut hard rather than lost', function (): void {
    $parts = TelegramText::chunks(str_repeat('x', 5000));

    expect($parts)->toHaveCount(2)
        ->and(strlen($parts[0]))->toBe(4096)
        ->and(strlen($parts[1]))->toBe(904);
});

test('a text too long for five messages ends with the mark after the fifth', function (): void {
    $parts = TelegramText::chunks(str_repeat("words and more words\n", 2000), 1000);

    expect($parts)->toHaveCount(TelegramText::MAX_PARTS)
        ->and(end($parts))->toEndWith('…');
});

test('a stray byte is dropped rather than turning the whole message empty', function (): void {
    $html = "<b>Caf\xE9 Lalibela</b>\nሰላም";

    $clip = TelegramText::clip($html);
    expect($clip)->not->toBe('')->toContain('Lalibela')->toContain('ሰላም')
        ->and(TelegramHtml::problem($clip))->toBeNull()
        ->and(TelegramText::chunks($html))->toHaveCount(1);
});
