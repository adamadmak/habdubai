<?php

use App\Models\Business;
use App\Models\BusinessReport;
use App\Models\Conversation;
use App\Models\DirectoryCity;
use App\Support\MapLocation;
use Laravel\Sanctum\Sanctum;

function dubai(): DirectoryCity
{
    return DirectoryCity::query()->where('key', 'dubai')->firstOrFail();
}

function business(array $attributes = []): Business
{
    static $n = 0;
    $n++;

    return Business::query()->create($attributes + [
        'city_id' => dubai()->id, 'category' => 'restaurant', 'name' => 'Habesha Restaurant '.$n, 'area' => 'Deira, Al Rigga',
        'phone' => '+971501234567', 'status' => 'approved',
    ]);
}

/** Press a directory button in the member's DM. */
function dirTap(int $userId, string $data): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'callback_query' => [
            'id' => 'cb'.nextUpdateId(),
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Abebe', 'language_code' => 'en'],
            'message' => ['message_id' => 77, 'chat' => ['id' => $userId, 'type' => 'private']],
            'data' => $data,
        ],
    ])->assertOk();
}

function sharePin(int $userId, float $lat, float $lng): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), telegramMessage($userId, ['location' => ['latitude' => $lat, 'longitude' => $lng]]))->assertOk();
}

test('the UAE cities are seeded and listed in order', function (): void {
    expect(DirectoryCity::listed()->pluck('name_en')->take(4)->all())->toBe(['Dubai', 'Sharjah', 'Abu Dhabi', 'Ajman'])
        ->and(DirectoryCity::query()->count())->toBe(8);
});

test('/directory shows cities with counts of listed businesses only', function (): void {
    joinMember(300);
    business();
    business();
    business(['status' => 'pending']);

    say(300, '/directory');

    $buttons = collect(lastMessage()['reply_markup']['inline_keyboard'])->flatten(1);
    expect(lastText())->toContain('Habesha business directory')
        ->and($buttons->firstWhere('callback_data', 'd:city:'.dubai()->id)['text'])->toBe('Dubai (2)')
        ->and($buttons->pluck('callback_data'))->toContain('d:search', 'd:add');
});

test('city → category → list → one business with a map pin', function (): void {
    joinMember(301);
    $pinned = business(['name' => 'Addis Cafe', 'category' => 'cafe', 'latitude' => 25.2697, 'longitude' => 55.3095, 'verified' => true]);
    business(['name' => 'Selam Restaurant', 'category' => 'restaurant']);

    dirTap(301, 'd:city:'.dubai()->id);
    $edit = collect(telegramCalls('editMessageText'))->last();
    $cats = collect($edit['reply_markup']['inline_keyboard'])->flatten(1)->pluck('callback_data');
    expect($edit['text'])->toContain('Dubai')->and($cats)->toContain('d:cat:'.dubai()->id.':cafe:1', 'd:cat:'.dubai()->id.':restaurant:1')
        ->and($cats)->not->toContain('d:cat:'.dubai()->id.':salon:1');

    dirTap(301, 'd:cat:'.dubai()->id.':cafe:1');
    $list = collect(telegramCalls('editMessageText'))->last();
    expect($list['text'])->toContain('Cafe and coffee · Dubai')->toContain('1. <b>Addis Cafe</b> ✅')->toContain('Deira, Al Rigga')
        ->and(collect($list['reply_markup']['inline_keyboard'])->flatten(1)->pluck('callback_data'))->toContain('d:b:'.$pinned->id);

    dirTap(301, 'd:b:'.$pinned->id);
    $venue = collect(telegramCalls('sendVenue'))->last();
    expect($venue['latitude'])->toBe(25.2697)->and($venue['title'])->toBe('Addis Cafe')
        ->and(lastText())->toContain('Addis Cafe')->toContain('✅ Verified')->toContain('+971501234567')
        ->and(collect(lastMessage()['reply_markup']['inline_keyboard'])->flatten(1)->pluck('url')->filter()->first())->toBe('https://maps.google.com/?q=25.2697,55.3095');
});

test('a long category pages eight at a time', function (): void {
    joinMember(302);
    foreach (range(1, 10) as $i) {
        business(['name' => sprintf('Shop %02d', $i), 'category' => 'grocery']);
    }

    dirTap(302, 'd:cat:'.dubai()->id.':grocery:2');

    $page = collect(telegramCalls('editMessageText'))->last();
    expect($page['text'])->toContain('2/2')->toContain('9. <b>Shop 09</b>')->not->toContain('Shop 08')
        ->and(collect($page['reply_markup']['inline_keyboard'])->flatten(1)->pluck('callback_data'))->toContain('d:cat:'.dubai()->id.':grocery:1');
});

test('search by name or area finds listed businesses only', function (): void {
    joinMember(303);
    business(['name' => 'Lalibela Restaurant']);
    business(['name' => 'Lalibela Salon', 'status' => 'pending']);

    dirTap(303, 'd:search');
    expect(Conversation::query()->where('telegram_user_id', 303)->value('state'))->toBe('dir_search');

    say(303, 'lalibela');
    expect(lastText())->toContain('Lalibela Restaurant')->not->toContain('Lalibela Salon');

    dirTap(303, 'd:search');
    say(303, 'Zzzz');
    expect(lastText())->toContain('No business matches');
});

test('a member suggests a business with a map pin; it waits for a moderator', function (): void {
    $member = joinMember(304);

    say(304, '/addbusiness');
    expect(lastText())->toContain('What is the business called?');

    say(304, 'https://spam.example.com');
    expect(lastText())->toContain('2 to 80 letters');

    say(304, 'Abyssinia Spices');
    expect(lastMessage()['reply_markup']['keyboard'][0][0]['text'])->toBe('Dubai');
    say(304, 'Sharjah');
    say(304, '🛒 Habesha shop');
    say(304, 'Al Nahda, near Sahara Centre');
    sharePin(304, 25.2995, 55.3712);
    say(304, '050 123 4567');
    say(304, 'Berbere, shiro, teff and coffee from home.');

    expect(lastText())->toContain('Please check')->toContain('Abyssinia Spices')->toContain('Al Nahda, near Sahara Centre, Sharjah')->toContain('+971501234567');

    say(304, '✅ Send');

    $b = Business::query()->first();
    expect($b->status)->toBe('pending')
        ->and($b->submitted_by)->toBe($member->id)
        ->and($b->city->key)->toBe('sharjah')
        ->and($b->category)->toBe('grocery')
        ->and($b->latitude)->toBe(25.2995)
        ->and($b->description)->toContain('Berbere')
        ->and(lastText())->toContain('once a moderator has checked it')
        ->and(Conversation::query()->where('telegram_user_id', 304)->value('state'))->toBe('idle');
});

test('a Google Maps link is accepted in place of a pin, and phone and description can be skipped', function (): void {
    joinMember(305);

    say(305, '/addbusiness');
    say(305, 'Meskel Tailoring');
    say(305, 'Dubai');
    say(305, '👗 Habesha clothes and tailoring');
    say(305, 'Satwa');
    say(305, 'not a link');
    expect(lastText())->toContain('not a map pin');
    say(305, 'https://www.google.com/maps/place/Satwa/@25.2224,55.2774,17z');
    say(305, '⏭ Skip');
    say(305, '⏭ Skip');
    say(305, '✅ Send');

    $b = Business::query()->first();
    expect($b->latitude)->toBe(25.2224)->and($b->longitude)->toBe(55.2774)
        ->and($b->maps_url)->toStartWith('https://www.google.com/maps/')
        ->and($b->phone)->toBeNull()->and($b->description)->toBeNull();
});

test('the same name in the same city is refused, and Cancel stops the flow', function (): void {
    joinMember(306);
    business(['name' => 'Habesha Corner']);

    say(306, '/addbusiness');
    say(306, 'habesha  corner!');
    say(306, 'Dubai');
    say(306, '🍽 Restaurant');
    expect(lastText())->toContain('already listed');

    say(306, '/addbusiness');
    say(306, 'New Place');
    say(306, '❌ Cancel');
    expect(Conversation::query()->where('telegram_user_id', 306)->value('state'))->toBe('idle')
        ->and(Business::query()->count())->toBe(1);
});

test('a member may have at most five suggestions waiting', function (): void {
    $member = joinMember(307);
    foreach (range(1, 5) as $i) {
        business(['status' => 'pending', 'submitted_by' => $member->id]);
    }

    say(307, '/addbusiness');

    expect(lastText())->toContain('5 suggestions waiting');
});

test('three reports hide an unverified business; a verified one stays until a moderator acts', function (): void {
    $plain = business();
    $verified = business(['verified' => true]);
    foreach ([310, 311, 312] as $i => $id) {
        joinMember($id, '+97150999000'.$i);
        dirTap($id, 'd:rep:'.$plain->id);
        dirTap($id, 'd:rep:'.$verified->id);
    }
    dirTap(310, 'd:rep:'.$plain->id);

    expect($plain->fresh()->status)->toBe('hidden')->and($plain->fresh()->reports_count)->toBe(3)
        ->and($verified->fresh()->status)->toBe('approved')
        ->and(BusinessReport::query()->count())->toBe(6);
});

test('the directory needs a finished sign-up', function (): void {
    say(320, '/directory', ['language_code' => 'en']);
    expect(lastText())->toContain('Send /start');
});

test('maps links and phone numbers are read sensibly', function (): void {
    expect(MapLocation::fromLink('https://maps.google.com/?q=25.1,55.2'))->toMatchArray(['latitude' => 25.1, 'longitude' => 55.2])
        ->and(MapLocation::fromLink('https://maps.app.goo.gl/AbCdEf123'))->toMatchArray(['latitude' => null, 'maps_url' => 'https://maps.app.goo.gl/AbCdEf123'])
        ->and(MapLocation::fromLink('https://example.com/maps'))->toBeNull()
        ->and(MapLocation::phone('04 123 4567'))->toBe('+97141234567')
        ->and(MapLocation::phone('+251 91 234 5678'))->toBe('+251912345678')
        ->and(MapLocation::phone('12'))->toBeNull();
});

// ---- admin --------------------------------------------------------------------

test('directory admin routes deny a guest', function (string $method, string $path): void {
    $this->json($method, $path)->assertStatus(401);
})->with([
    ['GET', '/api/admin/businesses'],
    ['POST', '/api/admin/businesses'],
    ['GET', '/api/admin/directory-cities'],
    ['POST', '/api/admin/directory-cities'],
]);

test('an admin reviews suggestions: approve lists it, reject keeps it out', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    $a = business(['status' => 'pending', 'name' => 'Pending One']);
    $b = business(['status' => 'pending', 'name' => 'Pending Two']);

    $this->getJson('/api/admin/businesses')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('counts.pending', 2)
        ->assertJsonPath('choices.categories.restaurant', '🍽 Restaurant');

    $this->postJson("/api/admin/businesses/{$a->id}/approve")->assertOk()->assertJsonPath('business.status', 'approved');
    $this->postJson("/api/admin/businesses/{$b->id}/reject", ['note' => 'Closed down'])->assertOk()->assertJsonPath('business.status', 'rejected');

    expect(Business::query()->listed()->pluck('name')->all())->toBe(['Pending One']);
});

test('an admin adds a business directly; a pasted maps link fills the pin and the phone is normalised', function (): void {
    Sanctum::actingAs(admin(), ['admin']);

    $this->postJson('/api/admin/businesses', ['name' => 'X', 'city_id' => 9999, 'category' => 'cafe', 'area' => 'Karama'])
        ->assertStatus(422)->assertJsonValidationErrors(['name', 'city_id']);

    $this->postJson('/api/admin/businesses', [
        'name' => 'Ethiopian Community Church', 'city_id' => dubai()->id, 'category' => 'worship', 'area' => 'Oud Metha',
        'maps_url' => 'https://www.google.com/maps/@25.2350,55.3150,16z', 'phone' => '04 123 4567', 'telegram' => '@ecc_dubai', 'verified' => true,
    ])->assertCreated()
        ->assertJsonPath('business.status', 'approved')
        ->assertJsonPath('business.latitude', 25.235)
        ->assertJsonPath('business.phone', '+97141234567')
        ->assertJsonPath('business.telegram', 'ecc_dubai')
        ->assertJsonPath('business.map_link', 'https://maps.google.com/?q=25.235,55.315');
});

test('approving a hidden business clears the reports that hid it', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    $b = business(['status' => 'hidden', 'reports_count' => 3]);
    foreach ([330, 331] as $id) {
        BusinessReport::query()->create(['member_id' => joinMember($id, '+9715088800'.$id)->id, 'business_id' => $b->id, 'created_at' => now()]);
    }

    $this->postJson("/api/admin/businesses/{$b->id}/approve")->assertOk()->assertJsonPath('business.reports_count', 0);
    expect(BusinessReport::query()->count())->toBe(0);
});

test('cities can be added, renamed and turned off; one with businesses cannot be deleted', function (): void {
    Sanctum::actingAs(admin(), ['admin']);

    $id = $this->postJson('/api/admin/directory-cities', ['name_en' => 'Riyadh', 'name_am' => 'ሪያድ', 'name_ti' => 'ሪያድ', 'country' => 'SA', 'sort' => 200])
        ->assertCreated()->assertJsonPath('city.key', 'riyadh')->json('city.id');

    $this->putJson("/api/admin/directory-cities/{$id}", ['name_en' => 'Riyadh', 'name_am' => 'ሪያድ', 'name_ti' => 'ሪያድ', 'country' => 'SA', 'active' => false])->assertOk();
    expect(DirectoryCity::listed()->pluck('name_en'))->not->toContain('Riyadh');

    $this->deleteJson("/api/admin/directory-cities/{$id}")->assertOk();

    business();
    $this->deleteJson('/api/admin/directory-cities/'.dubai()->id)->assertStatus(422);

    $this->getJson('/api/admin/directory-cities')->assertOk()->assertJsonPath('data.0.listed_count', 1);
});
