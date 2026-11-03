<?php

use App\Support\Phone;

test('normalises UAE and Ethiopian numbers to international digits', function (string $input, ?string $expected): void {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['+971 50 123 4567', '971501234567'],
    ['971501234567', '971501234567'],
    ['00971501234567', '971501234567'],
    ['0501234567', '971501234567'],
    ['+251 91 234 5678', '251912345678'],
    ['0912345678', '251912345678'],
    ['abc', null],
    ['12', null],
]);

test('works out the country code', function (): void {
    expect(Phone::country('971501234567'))->toBe('971')
        ->and(Phone::country('251912345678'))->toBe('251')
        ->and(Phone::country('447700900123'))->toBe('44')
        ->and(Phone::country('12025550123'))->toBe('1');
});

test('hashes the same number the same way and keeps the last four', function (): void {
    expect(Phone::hash('971501234567'))->toBe(Phone::hash('971501234567'))
        ->and(Phone::hash('971501234567'))->not->toBe(Phone::hash('971501234568'))
        ->and(Phone::last4('971501234567'))->toBe('4567');
});
