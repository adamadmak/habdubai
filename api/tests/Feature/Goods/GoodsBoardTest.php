<?php

use App\Models\Conversation;
use App\Models\GoodsMatch;
use App\Models\GoodsPost;
use App\Models\Member;
use App\Models\PlatformSetting;
use App\Models\Rating;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

const GGROUP = '-1005000';

/** A joined member with a name of their own and a phone made from the user id. */
function goodsMember(int $userId, string $name): Member
{
    $member = joinMember($userId, sprintf('+9715%08d', $userId));
    $member->forceFill(['display_name' => $name])->save();

    return $member;
}

function pressG(int $userId, string $data, int|string|null $chatId = null, string $type = 'private', int $messageId = 77): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'callback_query' => [
            'id' => 'cb'.nextUpdateId(),
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'X', 'language_code' => 'en'],
            'message' => ['message_id' => $messageId, 'chat' => ['id' => $chatId ?? $userId, 'type' => $type]],
            'data' => $data,
        ],
    ])->assertOk();
}

/** /travel all the way through, to Ethiopia from Dubai. */
function postTrip(int $userId, string $date = '20/11/2026', string $kilos = '10 kg'): GoodsPost
{
    foreach (['/travel', '🇦🇪 → 🇪🇹 To Ethiopia', $date, 'Dubai', 'Addis Ababa', $kilos, '👕 Clothes and shoes', '📄 Documents and letters', '✅ Done', '⏭ Skip', '📦 Post it'] as $text) {
        say($userId, $text);
    }

    return GoodsPost::query()->where('kind', 'traveller')->latest('id')->firstOrFail();
}

/** /send all the way through, to Ethiopia from Dubai. */
function postParcel(int $userId, string $date = '21/11/2026', string $kilos = '5 kg', string $what = 'Two dresses and a scarf for my mother'): GoodsPost
{
    foreach (['/send', '🇦🇪 → 🇪🇹 To Ethiopia', $date, 'Dubai', 'Addis Ababa', $kilos, '👕 Clothes and shoes', '✅ Done', $what, '📦 Post it'] as $text) {
        say($userId, $text);
    }

    return GoodsPost::query()->where('kind', 'sender')->latest('id')->firstOrFail();
}

/** A traveller (801) and a sender (802), introduced and agreed on 5 kg; returns the match. */
function agreedMatch(): GoodsMatch
{
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    $trip = postTrip(801);
    postParcel(802);
    pressG(802, 'g:i:'.$trip->id);
    $match = GoodsMatch::query()->sole();
    pressG(801, 'g:a:'.$match->id);
    pressG(801, 'g:ok:'.$match->id);
    pressG(802, 'g:ok:'.$match->id);

    return $match->fresh();
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 06:00:00', 'UTC')); // 10:00 in Dubai
    PlatformSetting::put('telegram_goods_group_id', GGROUP);
    PlatformSetting::put('telegram_bot_username', 'HabeshaDubaiBot');
});

test('a traveller posts a trip: checked step by step, previewed, recorded and mirrored with the rules in three languages', function (): void {
    goodsMember(801, 'Abebe K');

    say(801, '/travel');
    expect(collect(lastMessage()['reply_markup']['keyboard'])->flatten(1)->pluck('text')->all())->toBe(['🇦🇪 → 🇪🇹 To Ethiopia', '🇪🇹 → 🇦🇪 To the UAE', '❌ Cancel']);
    say(801, '🇦🇪 → 🇪🇹 To Ethiopia');
    say(801, '2026-10-01');
    expect(lastText())->toContain('from today up to four months');
    say(801, '20/11');
    expect(lastText())->toBe('From which city?');
    say(801, 'Dubai');
    say(801, 'Addis Ababa');
    say(801, '99');
    expect(lastText())->toContain('from 1 to 46');
    say(801, '10 kg');
    say(801, '✅ Done');
    expect(lastText())->toContain('Pick at least one');
    say(801, '👕 Clothes and shoes');
    expect(lastText())->toContain('Picked: 👕 Clothes and shoes');
    say(801, '✅ Done');
    say(801, 'I can bring some gold back for you');
    expect(lastText())->toContain('never carried');
    say(801, 'Open bags only, hand over in Deira by the 19th');
    expect(lastText())->toContain('This is how your post will look')->toContain('Traveller · Dubai → Addis Ababa')
        ->toContain('20 Nov 2026')->toContain('10 kg free')->toContain('Open bags only')->toContain('Abebe K ✅')->toContain('new on the board');
    say(801, '📦 Post it');

    $post = GoodsPost::query()->sole();
    expect($post->kind)->toBe('traveller')->and($post->direction)->toBe('to_et')->and($post->date->toDateString())->toBe('2026-11-20')
        ->and($post->city_from)->toBe('dubai')->and($post->city_to)->toBe('addis_ababa')
        ->and($post->kilos)->toBe(10)->and($post->kilos_left)->toBe(10)->and($post->categories)->toBe(['clothes'])
        ->and($post->price)->toBeNull()->and($post->group_message_id)->toBe(1)
        ->and(Conversation::query()->where('telegram_user_id', 801)->value('state'))->toBe('idle');

    $group = lastTo(GGROUP);
    expect($group['text'])->toContain('ተጓዥ · ዱባይ → አዲስ አበባ')
        ->toContain('No cash, gold, medicine')->toContain('ጥሬ ገንዘብ፣ ወርቅ')->toContain('ጥረ ገንዘብ፣ ወርቂ')
        ->and(buttonsOf($group))->toBe(['https://t.me/HabeshaDubaiBot?start=g'.$post->id]);
    expect(implode("\n", textsTo(801)))->toContain('Posted. It is on the goods board group too.')->toContain('No match yet');
});

test('a parcel that fits is shown the trip, and the traveller hears about the parcel', function (): void {
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    $trip = postTrip(801);
    $parcel = postParcel(802);

    $list = collect(sentMessages())->first(fn (array $m) => (string) $m['chat_id'] === '802' && str_contains($m['text'], 'These posts match yours'));
    expect($list['text'])->toContain('Traveller · Dubai → Addis Ababa')->and(buttonsOf($list))->toBe(['g:i:'.$trip->id]);

    $notice = lastTo(801);
    expect($notice['text'])->toContain('A new post matches yours')->toContain('Parcel · Dubai → Addis Ababa')->toContain('Two dresses')
        ->and(buttonsOf($notice))->toBe(['g:i:'.$parcel->id]);
});

test('posts that do not fit are not matched: other way, dates too far apart, or too heavy', function (): void {
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    $trip = postTrip(801, '20/11/2026', '5 kg');

    expect(postParcel(802, '25/11/2026')->candidates())->toBeEmpty()          // five days apart
        ->and(postParcel(802, '20/11/2026', '10 kg')->candidates())->toBeEmpty() // heavier than the room
        ->and(postParcel(802, '22/11/2026', '5 kg')->candidates()->pluck('id')->all())->toBe([$trip->id]);
});

test('interest needs your own post first, and the request goes out as soon as it is posted', function (): void {
    $traveller = goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    $trip = postTrip(801);

    pressG(802, 'g:i:'.$trip->id);
    expect(textsTo(802))->toContain('To ask this traveller, first post what you are sending. Your request goes out the moment you post.')
        ->and(lastText())->toContain('Around what date should it go?')
        ->and(Conversation::query()->where('telegram_user_id', 802)->value('state'))->toBe('gd_date');

    foreach (['21/11/2026', 'Dubai', 'Addis Ababa', '5 kg', '👕 Clothes and shoes', '✅ Done', 'Two dresses and a scarf for my mother', '📦 Post it'] as $text) {
        say(802, $text);
    }

    $match = GoodsMatch::query()->sole();
    expect($match->status)->toBe('requested')->and($match->owner_id)->toBe($traveller->id)->and($match->kilos)->toBe(5);

    $request = lastTo(801);
    expect($request['text'])->toContain('Sara T</b> is interested in your post')->toContain('Two dresses')->not->toContain('tg://user')
        ->and(buttonsOf($request))->toBe(['g:a:'.$match->id, 'g:d:'.$match->id]);
    expect(textsTo(802))->toContain('Request sent to Abebe K. We will tell you when they answer.');
});

test('the Interested link under the group card opens the bot and asks', function (): void {
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    $trip = postTrip(801);
    postParcel(802);

    say(802, '/start g'.$trip->id);

    expect(GoodsMatch::query()->sole()->post_id)->toBe($trip->id);
    say(802, '/start g'.$trip->id);
    expect(lastText())->toBe('You have already asked about this post.');
});

test('nobody can ask about their own post or a closed one', function (): void {
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    $trip = postTrip(801);

    pressG(801, 'g:i:'.$trip->id);
    expect(lastText())->toBe('This is your own post.');

    $trip->forceFill(['status' => 'closed'])->save();
    pressG(802, 'g:i:'.$trip->id);
    expect(lastText())->toBe('This post is no longer open.');
});

test('accepting introduces both with a link to each other; declining tells the asker politely', function (): void {
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    goodsMember(803, 'Hana G');
    $trip = postTrip(801);
    postParcel(802);
    postParcel(803, '19/11/2026', '2 kg', 'A box of documents for my brother');

    pressG(802, 'g:i:'.$trip->id);
    pressG(803, 'g:i:'.$trip->id);
    [$first, $second] = GoodsMatch::query()->orderBy('id')->get()->all();

    pressG(801, 'g:a:'.$first->id);
    expect($first->fresh()->status)->toBe('introduced')
        ->and(telegramCalls('editMessageReplyMarkup'))->toHaveCount(1);

    $to801 = collect(sentMessages())->last(fn (array $m) => (string) $m['chat_id'] === '801' && str_contains($m['text'], 'You are connected with'));
    $to802 = lastTo(802);
    expect($to801['text'])->toContain('Sara T')->toContain('tg://user?id=802')
        ->and($to802['text'])->toContain('You are connected with Abebe K')->toContain('tg://user?id=801')->toContain('never carry a parcel you did not see packed')
        ->and(buttonsOf($to802))->toBe(['g:ok:'.$first->id, 'g:x:'.$first->id]);

    pressG(801, 'g:d:'.$second->id);
    expect($second->fresh()->status)->toBe('declined')
        ->and(lastTo(803)['text'])->toBe('Abebe K cannot take this one. See other posts with /goods.')
        ->and(collect(textsTo(803))->contains(fn ($t) => str_contains($t, 'tg://user')))->toBeFalse();

    pressG(801, 'g:a:'.$second->id);
    expect(lastText())->toBe('This match is already closed.');
});

test('interest both ways counts as acceptance', function (): void {
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    $trip = postTrip(801);
    $parcel = postParcel(802);

    pressG(802, 'g:i:'.$trip->id);
    pressG(801, 'g:i:'.$parcel->id);

    expect(GoodsMatch::query()->count())->toBe(1)->and(GoodsMatch::query()->sole()->status)->toBe('introduced');
});

test('both pressing We agreed takes the kilos off the trip, closes the parcel and updates the group cards', function (): void {
    $match = agreedMatch();

    $trip = $match->travellerPost();
    $parcel = $match->senderPost();
    expect($match->status)->toBe('agreed')->and($match->checkin_on->toDateString())->toBe('2026-11-21')
        ->and($trip->kilos_left)->toBe(5)->and($trip->status)->toBe('open')
        ->and($parcel->status)->toBe('matched');

    expect(implode("\n", textsTo(801)))->toContain('Waiting for Sara T to press We agreed too');
    expect(lastTo(801)['text'])->toContain('Agreed with Sara T: 5 kg, Dubai → Addis Ababa, 20 Nov 2026. On 21 Nov 2026 we will ask');
    expect(lastTo(802)['text'])->toContain('Agreed with Abebe K');

    $edits = collect(telegramCalls('editMessageText'))->filter(fn ($c) => (string) $c['chat_id'] === GGROUP);
    $parcelEdit = $edits->first(fn ($c) => $c['message_id'] === $parcel->group_message_id && str_contains($c['text'], 'ዕቃ ·'));
    expect($parcelEdit['text'])->toStartWith('✅ ተገናኝቷል')->and($parcelEdit['reply_markup'])->toBe(['inline_keyboard' => []]);
    expect($edits->contains(fn ($c) => str_contains($c['text'], '5 ኪሎ ቦታ')))->toBeTrue();
});

test('a trip with no room left is refused at agreement', function (): void {
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    goodsMember(803, 'Hana G');
    $trip = postTrip(801, '20/11/2026', '5 kg');
    postParcel(802, '20/11/2026', '4 kg');
    postParcel(803, '20/11/2026', '4 kg', 'Coffee pot and two shirts for my aunt');
    pressG(802, 'g:i:'.$trip->id);
    pressG(803, 'g:i:'.$trip->id);
    [$a, $b] = GoodsMatch::query()->orderBy('id')->get()->all();
    pressG(801, 'g:a:'.$a->id);
    pressG(801, 'g:a:'.$b->id);

    pressG(801, 'g:ok:'.$a->id);
    pressG(802, 'g:ok:'.$a->id);
    pressG(801, 'g:ok:'.$b->id);
    pressG(803, 'g:ok:'.$b->id);

    expect($a->fresh()->status)->toBe('agreed')->and($b->fresh()->status)->toBe('cancelled')
        ->and($trip->fresh()->kilos_left)->toBe(1)
        ->and(lastTo(803)['text'])->toBe('The traveller has no room left for this parcel.');
});

test('calling off an agreement gives the kilos back and reopens the parcel', function (): void {
    $match = agreedMatch();

    pressG(802, 'g:x:'.$match->id);

    expect($match->fresh()->status)->toBe('cancelled')
        ->and($match->travellerPost()->fresh()->kilos_left)->toBe(10)
        ->and($match->senderPost()->fresh()->status)->toBe('open')
        ->and(lastTo(802)['text'])->toBe('Done: this match is cancelled.')
        ->and(lastTo(801)['text'])->toContain('Sara T is not going ahead with your match');
});

test('the day after the flight both are asked; two yeses complete it and open a rating hidden until both rate', function (): void {
    $match = agreedMatch();
    $traveller = Member::findByTelegramUser(801);
    $sender = Member::findByTelegramUser(802);

    $this->travelTo(CarbonImmutable::parse('2026-11-21 02:00:00', 'UTC')); // 06:00 in Dubai: too early
    $this->artisan('goods:chores')->assertSuccessful();
    expect(collect(sentMessages())->contains(fn ($m) => str_contains($m['text'], 'arrive safely')))->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-11-21 06:00:00', 'UTC'));
    $this->artisan('goods:chores')->assertSuccessful();
    $ask = lastTo(802);
    expect($ask['text'])->toBe('📦 Did the parcel with Abebe K (Dubai → Addis Ababa, 20 Nov 2026) arrive safely?')
        ->and(buttonsOf($ask))->toBe(["g:c:{$match->id}:yes", "g:c:{$match->id}:no", "g:c:{$match->id}:wait"])
        ->and(lastTo(801)['text'])->toContain('arrive safely');

    // Asked once; the next hour sends nothing new.
    $before = count(sentMessages());
    $this->travelTo(now()->addHour());
    $this->artisan('goods:chores')->assertSuccessful();
    expect(count(sentMessages()))->toBe($before);

    pressG(801, "g:c:{$match->id}:yes");
    expect(lastText())->toBe('Thank you, noted.')->and($match->fresh()->status)->toBe('agreed');
    pressG(802, "g:c:{$match->id}:yes");

    expect($match->fresh()->status)->toBe('completed')
        ->and($traveller->fresh()->trips_completed)->toBe(1)->and($sender->fresh()->trips_completed)->toBe(1);
    $rate = lastTo(801);
    expect($rate['text'])->toContain('Delivered. How was Sara T?')->and(buttonsOf($rate))->toHaveCount(5);

    pressG(801, "g:r:{$match->id}:5");
    expect(lastText())->toBe('Thank you. Your rating is saved.')->and($sender->fresh()->rating_count)->toBe(0);
    pressG(801, "g:r:{$match->id}:3");
    expect(lastText())->toBe('You have already rated this match.');

    pressG(802, "g:r:{$match->id}:4");
    expect($sender->fresh()->rating_count)->toBe(1)->and((float) $sender->fresh()->rating_avg)->toBe(5.0)
        ->and((float) $traveller->fresh()->rating_avg)->toBe(4.0);

    say(801, '/profile');
    expect(lastText())->toContain('Goods board deals: 1 · ⭐ 4.0 (1)');
});

test('one side rating alone shows after a week; "not yet" asks again two days later', function (): void {
    $match = agreedMatch();
    $sender = Member::findByTelegramUser(802);

    $this->travelTo(CarbonImmutable::parse('2026-11-21 06:00:00', 'UTC'));
    $this->artisan('goods:chores');
    pressG(802, "g:c:{$match->id}:wait");
    expect(lastText())->toBe('OK. We will ask again in two days.');
    pressG(801, "g:c:{$match->id}:yes");

    $this->travelTo(CarbonImmutable::parse('2026-11-23 06:00:00', 'UTC'));
    $this->artisan('goods:chores');
    expect(lastTo(802)['text'])->toContain('arrive safely')
        ->and(collect(sentMessages())->filter(fn ($m) => (string) $m['chat_id'] === '801' && str_contains($m['text'], 'arrive safely')))->toHaveCount(1);

    pressG(802, "g:c:{$match->id}:yes");
    pressG(801, "g:r:{$match->id}:4");
    expect($sender->fresh()->rating_count)->toBe(0);

    $this->travelTo(now()->addDays(8));
    $this->artisan('goods:chores');
    expect($sender->fresh()->rating_count)->toBe(1)->and((float) $sender->fresh()->rating_avg)->toBe(4.0)
        ->and(Rating::query()->sole()->counted_at)->not->toBeNull();
});

test('a match nobody answers is settled after two weeks: one yes completes it, silence leaves it unconfirmed', function (): void {
    $match = agreedMatch();
    $this->travelTo(CarbonImmutable::parse('2026-11-21 06:00:00', 'UTC'));
    $this->artisan('goods:chores');

    $this->travelTo(CarbonImmutable::parse('2026-12-06 06:00:00', 'UTC'));
    $this->artisan('goods:chores');

    expect($match->fresh()->status)->toBe('unconfirmed')
        ->and(Member::findByTelegramUser(801)->trips_completed)->toBe(0);
});

test('a "no" at check-in goes to the moderators, and two failed deals take a member off the board', function (): void {
    $moderator = goodsMember(805, 'Moderator M');
    $moderator->forceFill(['role' => 'moderator'])->save();
    $match = agreedMatch();
    $traveller = Member::findByTelegramUser(801);

    $this->travelTo(CarbonImmutable::parse('2026-11-21 06:00:00', 'UTC'));
    $this->artisan('goods:chores');
    pressG(802, "g:c:{$match->id}:no");

    expect($match->fresh()->status)->toBe('disputed')
        ->and(lastTo(802)['text'])->toContain('A moderator will contact you both')
        ->and(lastTo(801)['text'])->toContain('A moderator will contact you both')
        ->and(lastTo(805)['text'])->toContain("Goods dispute #{$match->id}")->toContain('Abebe K (traveller)')->toContain('Sara T (sender)');

    Sanctum::actingAs(admin(), ['admin']);

    $this->getJson('/api/admin/goods/matches?status=disputed')->assertOk()
        ->assertJsonPath('data.0.id', $match->id)->assertJsonPath('data.0.traveller.name', 'Abebe K')->assertJsonPath('data.0.checkin.sender', 'no');

    $this->postJson("/api/admin/goods/matches/{$match->id}/resolve", ['outcome' => 'failed'])->assertStatus(422)->assertJsonValidationErrors('failed_member_id');
    $this->postJson("/api/admin/goods/matches/{$match->id}/resolve", ['outcome' => 'failed', 'failed_member_id' => 9999])->assertStatus(422);
    $this->postJson("/api/admin/goods/matches/{$match->id}/resolve", ['outcome' => 'failed', 'failed_member_id' => $traveller->id, 'note' => 'Never delivered'])
        ->assertOk()->assertJsonPath('match.status', 'failed')->assertJsonPath('match.traveller.failures_year', 1);
    expect(lastTo(801)['text'])->toContain('A moderator closed your match (Dubai → Addis Ababa, 20 Nov 2026): it failed');

    // A second failure within the year: the traveller's open posts are hidden and new ones refused.
    $open = GoodsPost::query()->create([
        'member_id' => $traveller->id, 'kind' => 'traveller', 'direction' => 'to_ae', 'date' => '2026-12-20', 'city_from' => 'addis_ababa',
        'city_to' => 'dubai', 'kilos' => 10, 'kilos_left' => 10, 'categories' => ['food'], 'status' => 'open',
    ]);
    $second = GoodsMatch::query()->create([
        'post_id' => $match->post_id, 'requester_post_id' => $match->requester_post_id, 'owner_id' => $match->owner_id,
        'requester_id' => $moderator->id, 'status' => 'disputed', 'kilos' => 5,
    ]);
    $this->postJson("/api/admin/goods/matches/{$second->id}/resolve", ['outcome' => 'failed', 'failed_member_id' => $traveller->id])->assertOk();

    expect($open->fresh()->status)->toBe('hidden')->and(GoodsMatch::blocked($traveller))->toBeTrue()
        ->and(lastTo(801)['text'])->toContain('hidden after two failed deals');
    say(801, '/travel');
    expect(lastText())->toContain('You cannot use the goods board after two failed deals');
});

test('a moderator can decide a dispute as delivered, which counts the deal', function (): void {
    $match = agreedMatch();
    $match->forceFill(['status' => 'disputed'])->save();
    Sanctum::actingAs(admin(), ['admin']);

    $this->postJson("/api/admin/goods/matches/{$match->id}/resolve", ['outcome' => 'completed', 'note' => 'It was a misunderstanding'])
        ->assertOk()->assertJsonPath('match.status', 'completed');

    expect(Member::findByTelegramUser(802)->trips_completed)->toBe(1)
        ->and(implode("\n", textsTo(802)))->toContain('How was Abebe K?')->toContain('it counts as delivered');

    $this->postJson("/api/admin/goods/matches/{$match->id}/resolve", ['outcome' => 'cancelled'])->assertStatus(422);
});

test('posts close after their date, and a parcel waits three more days for a later traveller', function (): void {
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    $trip = postTrip(801, '20/11/2026');
    $parcel = postParcel(802, '20/11/2026');

    $this->travelTo(CarbonImmutable::parse('2026-11-21 06:00:00', 'UTC'));
    $this->artisan('goods:chores');
    expect($trip->fresh()->status)->toBe('expired')->and($parcel->fresh()->status)->toBe('open');
    expect(collect(telegramCalls('editMessageText'))->last()['text'])->toStartWith('⌛ ቀኑ አልፏል');

    $this->travelTo(CarbonImmutable::parse('2026-11-24 06:00:00', 'UTC'));
    $this->artisan('goods:chores');
    expect($parcel->fresh()->status)->toBe('expired');
});

test('three open posts of a kind is the limit, and /mygoods lists and closes them', function (): void {
    goodsMember(801, 'Abebe K');
    postTrip(801, '20/11/2026');
    postTrip(801, '25/11/2026');
    $third = postTrip(801, '30/11/2026');

    say(801, '/travel');
    expect(lastText())->toBe('You already have 3 open posts of this kind. Close one in /mygoods first.');

    say(801, '/mygoods');
    expect(lastText())->toContain('Your posts and deals')->toContain('3. 🧳 Dubai → Addis Ababa · 30 Nov · 10 kg')
        ->and(buttonsOf(lastMessage()))->toContain('g:z:'.$third->id);

    pressG(801, 'g:z:'.$third->id);
    expect($third->fresh()->status)->toBe('closed')->and(lastText())->toBe('Post closed.');
    say(801, '/travel');
    expect(lastText())->toBe('Which way?');
});

test('/goods shows the board and browsing lists open posts with Interested buttons', function (): void {
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    $trip = postTrip(801);

    say(802, '/goods');
    expect(lastText())->toContain('Goods board')->and(buttonsOf(lastMessage()))->toBe(['g:b:traveller:to_et', 'g:b:traveller:to_ae', 'g:b:sender:to_et', 'g:b:sender:to_ae']);

    pressG(802, 'g:b:traveller:to_et');
    expect(lastText())->toContain('Travellers → Ethiopia')->toContain('1. 🧳')->and(buttonsOf(lastMessage()))->toBe(['g:i:'.$trip->id]);

    pressG(802, 'g:b:sender:to_ae');
    expect(lastText())->toBe('Nothing open here yet.');
});

test('the admin lists goods posts and hides one: the group card goes and the owner is told', function (): void {
    goodsMember(801, 'Abebe K');
    goodsMember(802, 'Sara T');
    $trip = postTrip(801);
    postParcel(802);
    pressG(802, 'g:i:'.$trip->id);
    Sanctum::actingAs(admin(), ['admin']);

    $this->getJson('/api/admin/goods/posts?kind=traveller')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.route', 'Dubai → Addis Ababa')->assertJsonPath('data.0.member.name', 'Abebe K');

    $this->postJson("/api/admin/goods/posts/{$trip->id}/hide", ['reason' => 'Asked for money to carry'])->assertOk()->assertJsonPath('post.status', 'hidden');

    expect(telegramCalls('deleteMessage'))->toHaveCount(1)
        ->and(GoodsMatch::query()->sole()->status)->toBe('cancelled')
        ->and(lastTo(801)['text'])->toBe('A moderator removed your goods post (Dubai → Addis Ababa, 20 Nov 2026): Asked for money to carry');

    $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('goods.open_posts', 1)->assertJsonPath('goods.disputes', 0);
});

test('the goods admin is for admins only', function (): void {
    $this->getJson('/api/admin/goods/posts')->assertUnauthorized();
    $this->getJson('/api/admin/goods/matches')->assertUnauthorized();
});
