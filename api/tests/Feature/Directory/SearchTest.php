<?php

use App\Models\Business;
use App\Models\DirectoryCity;
use App\Support\Search;

test('a search takes % _ ! and a backslash as plain characters, ignoring case, across columns', function (): void {
    $city = DirectoryCity::query()->firstOrFail()->id;
    foreach ([['100% Habesha', 'Deira'], ['Cafe_One', 'Naif'], ['Wow! Coffee', 'Karama'], ['Back\\slash', 'Qusais'], ['Plain', 'Bur Dubai']] as [$name, $area]) {
        Business::query()->create(['city_id' => $city, 'category' => 'restaurant', 'name' => $name, 'area' => $area, 'status' => 'approved']);
    }

    $found = fn (string $term) => Search::anyColumn(Business::query(), ['name', 'area'], $term)->orderBy('name')->pluck('name')->all();

    expect($found('%'))->toBe(['100% Habesha'])
        ->and($found('_'))->toBe(['Cafe_One'])
        ->and($found('!'))->toBe(['Wow! Coffee'])
        ->and($found('\\'))->toBe(['Back\\slash'])
        ->and($found('COFFEE'))->toBe(['Wow! Coffee'])
        ->and($found('bur dub'))->toBe(['Plain']);
});

test('a search never sends a backslash in its SQL: on Postgres it breaks every query with a second placeholder', function (): void {
    // These tests run on SQLite, which cannot show the failure, so the query itself is what is held to it.
    $sql = Search::anyColumn(Business::query(), ['name', 'area'], 'x')->where('status', 'approved')->toSql();

    expect($sql)->not->toContain('\\')->and(substr_count($sql, '?'))->toBe(3);
});
