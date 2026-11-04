<?php

use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\BusinessOwner;
use App\Models\BusinessRating;
use App\Models\DirectoryCity;
use App\Models\Member;
use Laravel\Sanctum\Sanctum;

function ratedPlace(string $name, array $attributes = []): Business
{
    return Business::query()->create($attributes + [
        'city_id' => DirectoryCity::query()->firstOrFail()->id,
        'category' => 'restaurant',
        'name' => $name,
        'area' => 'Deira',
        'status' => 'approved',
    ]);
}

function ratingMember(int $userId, ?string $name = null): Member
{
    $member = joinMember($userId, sprintf('+9715%08d', $userId));
    if ($name !== null) {
        $member->forceFill(['display_name' => $name])->save();
    }

    return $member;
}

/** A website account for this Telegram id, signed in. */
function ratingAccount(int $userId, ?Member $member = null): string
{
    $owner = BusinessOwner::query()->create(['telegram_user_id' => $userId, 'member_id' => $member?->id, 'display_name' => 'Account '.$userId]);

    return $owner->createToken('owner', ['owner'])->plainTextToken;
}

function pressD(int $userId, string $data): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'callback_query' => [
            'id' => 'cb'.nextUpdateId(),
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'X', 'language_code' => 'en'],
            'message' => ['message_id' => 70, 'chat' => ['id' => $userId, 'type' => 'private']],
            'data' => $data,
        ],
    ])->assertOk();
}

test('a member rates a place on the website, may change it, and the page shows it', function (): void {
    $place = ratedPlace('Dukem Grill');
    $member = ratingMember(901);
    $other = ratingMember(902);
    $token = ratingAccount(901, $member);

    $this->withToken($token)->getJson("/api/owner/directory/{$place->slug}/rating")->assertOk()
        ->assertJsonPath('can_rate', true)->assertJsonPath('stars', null);

    $this->withToken($token)->postJson("/api/owner/directory/{$place->slug}/rating", ['stars' => 9])->assertStatus(422);
    $this->withToken($token)->postJson("/api/owner/directory/{$place->slug}/rating", ['stars' => 2])->assertOk()
        ->assertJsonPath('status', 'saved')->assertJsonPath('stars', 2)->assertJsonPath('rating', 2)->assertJsonPath('ratings', 1);

    // A second press changes it rather than adding one.
    $this->withToken($token)->postJson("/api/owner/directory/{$place->slug}/rating", ['stars' => 5])->assertOk()
        ->assertJsonPath('rating', 5)->assertJsonPath('ratings', 1);
    BusinessRating::give($other, $place, 3);

    $this->getJson("/api/public/businesses/{$place->slug}")->assertOk()
        ->assertJsonPath('business.rating', 4)->assertJsonPath('business.ratings', 2)
        ->assertJsonPath('business.top', false)
        ->assertJsonPath('business.rating_breakdown.0', ['stars' => 5, 'count' => 1])
        ->assertJsonPath('business.rating_breakdown.2', ['stars' => 3, 'count' => 1]);

    // Without moving "last updated", which the page shows.
    expect(Business::query()->find($place->id)->updated_at->toIso8601String())->toBe($place->updated_at->toIso8601String());
});

test('only members may rate, an owner cannot rate their own place, and a hidden place takes no stars', function (): void {
    $member = ratingMember(901);
    $place = ratedPlace('Dukem Grill');
    $this->postJson("/api/owner/directory/{$place->slug}/rating", ['stars' => 5])->assertUnauthorized();

    // A website account that never joined the community.
    $outsider = ratingAccount(950);
    $this->withToken($outsider)->getJson("/api/owner/directory/{$place->slug}/rating")->assertOk()
        ->assertJsonPath('can_rate', false)->assertJsonPath('blocked_by', 'not_member');
    $this->withToken($outsider)->postJson("/api/owner/directory/{$place->slug}/rating", ['stars' => 5])->assertStatus(403)->assertJsonPath('status', 'not_member');

    // The owner's own listing.
    $token = ratingAccount(901, $member);
    $this->app['auth']->forgetGuards(); // The test keeps one signed-in user per run until told otherwise.
    $owner = BusinessOwner::query()->where('telegram_user_id', 901)->sole();
    $mine = ratedPlace('My Shop', ['owner_id' => $owner->id]);
    $this->withToken($token)->getJson("/api/owner/directory/{$mine->slug}/rating")->assertOk()->assertJsonPath('blocked_by', 'own');
    $this->withToken($token)->postJson("/api/owner/directory/{$mine->slug}/rating", ['stars' => 5])->assertStatus(403)->assertJsonPath('status', 'own');
    expect($mine->fresh()->rating_count)->toBe(0);

    $hidden = ratedPlace('Gone', ['status' => 'hidden']);
    $this->withToken($token)->postJson("/api/owner/directory/{$hidden->slug}/rating", ['stars' => 5])->assertNotFound();
});

test('the directory lists the best rated first: stars beat verified, one rating does not beat many, poor ratings fall below no ratings', function (): void {
    $proven = ratedPlace('Proven');
    $oneFive = ratedPlace('One Five');
    $verified = ratedPlace('Verified Unrated', ['verified' => true]);
    $plain = ratedPlace('Plain Unrated');
    $poor = ratedPlace('Poor');

    foreach (range(1, 8) as $i) {
        BusinessRating::give(ratingMember(910 + $i), $proven, 5);
    }
    BusinessRating::give(Member::findByTelegramUser(911), $oneFive, 5);
    foreach (range(1, 4) as $i) {
        BusinessRating::give(Member::findByTelegramUser(910 + $i), $poor, 2);
    }

    expect(collect($this->getJson('/api/public/businesses')->assertOk()->json('data'))->pluck('name')->all())
        ->toBe(['Proven', 'One Five', 'Verified Unrated', 'Plain Unrated', 'Poor']);

    $this->getJson('/api/public/businesses')->assertJsonPath('data.0.top', true)->assertJsonPath('data.0.rating', 5)->assertJsonPath('data.0.ratings', 8)
        ->assertJsonPath('data.3.rating', null)->assertJsonPath('data.3.ratings', 0);

    // "More like this" on a place's page, the front page and the bot's list read the same order.
    $this->getJson("/api/public/businesses/{$plain->slug}")->assertOk()->assertJsonPath('nearby.0.name', 'Proven');
    $this->getJson('/api/public/home')->assertOk()->assertJsonPath('businesses.0.name', 'Proven');

    $city = DirectoryCity::query()->firstOrFail();
    pressD(911, 'd:cat:'.$city->id.':restaurant:1');
    $list = collect(telegramCalls('editMessageText'))->last()['text'];
    expect(strpos($list, 'Proven'))->toBeLessThan(strpos($list, 'One Five'))
        ->and(strpos($list, 'Plain Unrated'))->toBeLessThan(strpos($list, 'Poor'))
        ->and($list)->toContain('🏅 ⭐ 5.0 (8)');

    expect($verified->fresh()->rating_count)->toBe(0);
});

test('a member rates from the bot: the card has the stars, and another press changes them', function (): void {
    $place = ratedPlace('Dukem Grill');
    $member = ratingMember(901);
    BusinessRating::give(ratingMember(902), $place, 4);

    pressD(901, 'd:b:'.$place->id);
    $card = lastTo(901);
    expect($card['text'])->toContain('⭐ 4.0 (1)')->toContain('Been here? Rate it with the stars below')
        ->and(buttonsOf($card))->toContain('d:rate:'.$place->id.':1')->toContain('d:rate:'.$place->id.':5');

    pressD(901, 'd:rate:'.$place->id.':5');
    expect(collect(telegramCalls('answerCallbackQuery'))->last()['text'])->toContain('You gave Dukem Grill 5 ⭐')
        ->and((float) $place->fresh()->rating_avg)->toBe(4.5)->and($place->fresh()->rating_count)->toBe(2);

    pressD(901, 'd:rate:'.$place->id.':1');
    expect((float) $place->fresh()->rating_avg)->toBe(2.5)->and($place->fresh()->rating_count)->toBe(2)
        ->and(BusinessRating::query()->where('member_id', $member->id)->sole()->stars)->toBe(1);

    // A number that is not a star, and an owner on their own place.
    pressD(901, 'd:rate:'.$place->id.':9');
    expect($place->fresh()->rating_count)->toBe(2);

    $owner = BusinessOwner::query()->create(['telegram_user_id' => 901, 'member_id' => $member->id, 'display_name' => 'Owner']);
    $mine = ratedPlace('My Shop', ['owner_id' => $owner->id]);
    pressD(901, 'd:rate:'.$mine->id.':5');
    expect(collect(telegramCalls('answerCallbackQuery'))->last()['text'])->toBe('You cannot rate your own business.')
        ->and($mine->fresh()->rating_count)->toBe(0);
});

test('the bot\'s free-text answers list places best rated first too', function (): void {
    $plain = ratedPlace('Alpha Kitchen');
    $proven = ratedPlace('Zed Kitchen');
    foreach (range(1, 4) as $i) {
        BusinessRating::give(ratingMember(930 + $i), $proven, 5);
    }

    say(931, 'restaurant in deira');
    expect(strpos(lastText(), 'Zed Kitchen'))->toBeLessThan(strpos(lastText(), 'Alpha Kitchen'));

    expect($plain->fresh()->rating_count)->toBe(0);
});

test('a member who comes to own a place stops counting towards it', function (): void {
    $place = ratedPlace('Dukem Grill');
    $member = ratingMember(901);
    BusinessRating::give($member, $place, 5);
    BusinessRating::give(ratingMember(902), $place, 3);
    expect((float) $place->fresh()->rating_avg)->toBe(4.0);

    $owner = BusinessOwner::query()->create(['telegram_user_id' => 901, 'member_id' => $member->id, 'display_name' => 'Owner']);
    $claim = BusinessClaim::query()->create(['business_id' => $place->id, 'owner_id' => $owner->id, 'status' => 'pending']);
    Sanctum::actingAs(admin(), ['admin']);

    $this->postJson("/api/admin/business-claims/{$claim->id}/approve")->assertOk();

    expect((float) $place->fresh()->rating_avg)->toBe(3.0)->and($place->fresh()->rating_count)->toBe(1)
        ->and(BusinessRating::query()->where('member_id', $member->id)->exists())->toBeFalse();
});

test('a moderator sees who rated and can take stars off; a banned member\'s stars stop counting and come back if unbanned', function (): void {
    $place = ratedPlace('Dukem Grill');
    $fake = ratingMember(901, 'Fake F');
    $real = ratingMember(902, 'Real R');
    BusinessRating::give($fake, $place, 5);
    BusinessRating::give($real, $place, 3);
    Sanctum::actingAs(admin(), ['admin']);

    $this->getJson('/api/admin/businesses/'.$place->id)->assertOk()
        ->assertJsonPath('business.rating_avg', 4)->assertJsonPath('business.rating_count', 2)
        ->assertJsonCount(2, 'business.ratings');
    $this->getJson('/api/admin/businesses?status=all')->assertOk()->assertJsonPath('data.0.rating_count', 2);

    $this->postJson('/api/admin/members/'.$fake->id.'/ban', ['reason' => 'Fake stars'])->assertOk();
    expect((float) $place->fresh()->rating_avg)->toBe(3.0)->and($place->fresh()->rating_count)->toBe(1);

    $this->postJson('/api/admin/members/'.$fake->id.'/unban')->assertOk();
    expect((float) $place->fresh()->rating_avg)->toBe(4.0)->and($place->fresh()->rating_count)->toBe(2);

    $rating = BusinessRating::query()->where('member_id', $fake->id)->sole();
    $other = ratedPlace('Other');
    $this->deleteJson("/api/admin/businesses/{$other->id}/ratings/{$rating->id}")->assertNotFound();
    $this->deleteJson("/api/admin/businesses/{$place->id}/ratings/{$rating->id}")->assertOk();
    expect((float) $place->fresh()->rating_avg)->toBe(3.0)->and($place->fresh()->rating_count)->toBe(1);

    $this->postJson('/api/admin/members/'.$real->id.'/ban')->assertOk();
    expect($place->fresh()->rating_avg)->toBeNull()->and($place->fresh()->rating_count)->toBe(0);
});
