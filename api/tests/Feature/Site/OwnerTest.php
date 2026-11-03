<?php

use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\BusinessOwner;
use App\Models\DirectoryCity;
use App\Models\OwnerLoginRequest;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

function pressLogin(int $userId, string $data, array $from = []): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'callback_query' => [
            'id' => 'cb'.nextUpdateId(),
            'from' => array_merge(['id' => $userId, 'is_bot' => false, 'first_name' => 'Meron', 'last_name' => 'Tesfaye', 'username' => 'meron_t', 'language_code' => 'en'], $from),
            'message' => ['message_id' => 51, 'chat' => ['id' => $userId, 'type' => 'private']],
            'data' => $data,
        ],
    ])->assertOk();
}

/** The six-digit code the bot last sent this person. */
function codeSentTo(int $userId): string
{
    preg_match('/<b>(\d{6})<\/b>/', lastTo($userId)['text'], $m);

    return $m[1] ?? '';
}

/** The whole "Continue with Telegram" round: start, open the bot, confirm, type the code. Returns the owner token. */
function ownerSignIn(int $userId): string
{
    $start = test()->postJson('/api/owner/login/start')->assertOk()->json();
    say($userId, '/start login_'.$start['ref'], ['language_code' => 'en']);
    pressLogin($userId, 'lg:ok:'.OwnerLoginRequest::query()->where('ref', $start['ref'])->value('id'));

    return test()->postJson('/api/owner/login/verify', ['ref' => $start['ref'], 'secret' => $start['secret'], 'code' => codeSentTo($userId)])->json('token');
}

function asOwner(string $token)
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

function asAdmin()
{
    app('auth')->forgetGuards();
    Sanctum::actingAs(User::query()->where('email', 'admin@example.com')->first() ?? admin(), ['admin']);

    return test();
}

function listing(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lalibela Restaurant',
        'category' => 'restaurant',
        'city_id' => DirectoryCity::query()->where('key', 'dubai')->value('id'),
        'area' => 'Al Karama, near the metro',
        'phone' => '+971 50 123 4567',
        'whatsapp' => '0501234567',
        'telegram' => '@lalibela_dxb',
        'website' => 'https://lalibela.example',
        'description' => 'Habesha food and coffee ceremony every Saturday.',
        'about' => 'Family kitchen since 2015. Injera baked fresh every morning.',
        'hours' => 'Every day 10:00–23:00',
        'languages' => ['am', 'ti', 'en'],
    ], $overrides);
}

beforeEach(function (): void {
    PlatformSetting::put('telegram_bot_username', 'HabeshaDubaiBot');
    PlatformSetting::put('site_url', 'https://habesha.example');
    jobsChannel('@habesha_channel');
});

// ---- signing in ---------------------------------------------------------------------

test('continue with Telegram: the person confirms in the bot, gets a code there, and types it on the website for a token, once', function (): void {
    $start = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36'])
        ->postJson('/api/owner/login/start')->assertOk()->json();
    expect($start['link'])->toBe('https://t.me/HabeshaDubaiBot?start=login_'.$start['ref'])
        ->and($start['link'])->not->toContain($start['secret'])
        ->and($start)->not->toHaveKey('code');

    $this->postJson('/api/owner/login/poll', ['ref' => $start['ref'], 'secret' => $start['secret']])->assertJson(['status' => 'pending']);

    say(9001, '/start login_'.$start['ref'], ['language_code' => 'en']);
    expect(lastMessage()['text'])->toContain('Sign in to the Habesha Dubai website?')->toContain('habesha.example')->toContain('Device: Chrome · Windows');

    $request = OwnerLoginRequest::query()->sole();
    pressLogin(9001, 'lg:ok:'.$request->id);
    $code = codeSentTo(9001);
    expect($code)->toMatch('/^\d{6}$/')->and(lastText())->toContain('Never give this code to anyone');

    // Confirmed in Telegram: the website learns that, but no token comes without the code.
    $this->postJson('/api/owner/login/poll', ['ref' => $start['ref'], 'secret' => 'wrong-secret'])->assertJson(['status' => 'unknown']);
    $this->postJson('/api/owner/login/poll', ['ref' => $start['ref'], 'secret' => $start['secret']])->assertExactJson(['status' => 'confirmed']);

    $wrong = $code === '000000' ? '111111' : '000000';
    $this->postJson('/api/owner/login/verify', ['ref' => $start['ref'], 'secret' => $start['secret'], 'code' => $wrong])
        ->assertJson(['status' => 'wrong', 'attempts_left' => 4]);
    $this->postJson('/api/owner/login/verify', ['ref' => $start['ref'], 'secret' => 'wrong-secret', 'code' => $code])->assertJson(['status' => 'unknown']);

    $done = $this->postJson('/api/owner/login/verify', ['ref' => $start['ref'], 'secret' => $start['secret'], 'code' => $code])->assertOk()->json();
    expect($done['status'])->toBe('approved')->and($done['token'])->toBeString()->and($done['owner']['name'])->toBe('Meron Tesfaye')
        ->and($done['owner']['member'])->toBeFalse();
    $this->postJson('/api/owner/login/verify', ['ref' => $start['ref'], 'secret' => $start['secret'], 'code' => $code])->assertJson(['status' => 'expired']);

    asOwner($done['token'])->getJson('/api/owner/me')->assertOk()->assertJsonPath('owner.username', 'meron_t');
    expect(BusinessOwner::query()->sole()->telegram_user_id)->toBe(9001);
});

test('a sign-in link sent to an owner by someone else gives that someone nothing: the code goes to the owner', function (): void {
    // The attacker starts a sign-in and sends the owner the bot link; the owner taps Yes.
    $attacker = $this->postJson('/api/owner/login/start')->json();
    say(9008, '/start login_'.$attacker['ref'], ['language_code' => 'en']);
    pressLogin(9008, 'lg:ok:'.OwnerLoginRequest::query()->sole()->id);

    // The attacker's page sees "confirmed" but has no code; guessing ends the attempt.
    $this->postJson('/api/owner/login/poll', ['ref' => $attacker['ref'], 'secret' => $attacker['secret']])->assertJson(['status' => 'confirmed']);
    foreach (range(1, 4) as $i) {
        $this->postJson('/api/owner/login/verify', ['ref' => $attacker['ref'], 'secret' => $attacker['secret'], 'code' => sprintf('%06d', $i)]);
    }
    $this->postJson('/api/owner/login/verify', ['ref' => $attacker['ref'], 'secret' => $attacker['secret'], 'code' => '999999'])
        ->assertJson(['status' => codeSentTo(9008) === '999999' ? 'approved' : 'locked']);
    $this->postJson('/api/owner/login/verify', ['ref' => $attacker['ref'], 'secret' => $attacker['secret'], 'code' => codeSentTo(9008)])
        ->assertJson(['status' => 'expired']);
    expect(\Laravel\Sanctum\PersonalAccessToken::query()->count())->toBe(0);
});

test('a member signs in under their community name and the account is linked', function (): void {
    $member = joinMember(9002, '+971500009002');
    $token = ownerSignIn(9002);

    asOwner($token)->getJson('/api/owner/me')->assertJsonPath('owner.name', 'Abebe K')->assertJsonPath('owner.member', true);
    expect(BusinessOwner::query()->sole()->member_id)->toBe($member->id);
});

test('only the person who opened the link can confirm it, and "It was not me" signs nobody in', function (): void {
    $start = $this->postJson('/api/owner/login/start')->json();
    say(9003, '/start login_'.$start['ref']);
    $request = OwnerLoginRequest::query()->sole();

    // Someone else opening the same link, or pressing the button, gets nowhere.
    say(9004, '/start login_'.$start['ref'], ['language_code' => 'en']);
    expect(lastText())->toContain('expired');
    pressLogin(9004, 'lg:ok:'.$request->id);
    expect($request->fresh()->status)->toBe('pending');

    pressLogin(9003, 'lg:no:'.$request->id);
    expect($request->fresh()->status)->toBe('declined')->and(lastText())->toContain('nobody was signed in');
    $this->postJson('/api/owner/login/poll', ['ref' => $start['ref'], 'secret' => $start['secret']])->assertJson(['status' => 'declined']);
    expect(BusinessOwner::query()->count())->toBe(0);
});

test('a sign-in link expires after ten minutes', function (): void {
    $start = $this->postJson('/api/owner/login/start')->json();
    $this->travel(11)->minutes();

    say(9005, '/start login_'.$start['ref'], ['language_code' => 'en']);
    expect(lastText())->toContain('expired');
    $this->postJson('/api/owner/login/poll', ['ref' => $start['ref'], 'secret' => $start['secret']])->assertJson(['status' => 'expired']);
});

test('a banned member cannot sign in', function (): void {
    $member = joinMember(9006, '+971500009006');
    $member->forceFill(['banned_at' => now()])->save();
    $start = $this->postJson('/api/owner/login/start')->json();
    say(9006, '/start login_'.$start['ref']);

    expect(OwnerLoginRequest::query()->sole()->telegram_user_id)->toBeNull();
    $this->postJson('/api/owner/login/poll', ['ref' => $start['ref'], 'secret' => $start['secret']])->assertJson(['status' => 'pending']);
});

test('owner and admin tokens never cross', function (): void {
    $token = ownerSignIn(9007);

    asOwner($token)->getJson('/api/admin/dashboard')->assertForbidden();
    asOwner($token)->getJson('/api/admin/businesses')->assertForbidden();

    $adminToken = admin()->createToken('admin', ['admin'])->plainTextToken;
    asOwner($adminToken)->getJson('/api/owner/businesses')->assertForbidden();

    app('auth')->forgetGuards();
    $this->withToken('nonsense')->getJson('/api/owner/businesses')->assertUnauthorized();
});

// ---- listings ------------------------------------------------------------------------

test('an owner submits a listing; the moderator approves it; it goes live on the site and the channel and the owner is told', function (): void {
    $token = ownerSignIn(9010);

    $created = asOwner($token)->postJson('/api/owner/businesses', listing())->assertCreated()->json('business');
    expect($created['status'])->toBe('pending')->and($created['phone'])->toBe('+971501234567')->and($created['whatsapp'])->toBe('+971501234567')
        ->and($created['telegram'])->toBe('lalibela_dxb')->and($created['slug'])->toBe('lalibela-restaurant-'.$created['id']);

    $this->getJson('/api/public/businesses/'.$created['slug'])->assertNotFound();

    asAdmin()->getJson('/api/admin/businesses?status=pending')->assertOk()
        ->assertJsonPath('data.0.owner.name', 'Meron Tesfaye')->assertJsonPath('data.0.languages', ['am', 'ti', 'en']);
    asAdmin()->postJson('/api/admin/businesses/'.$created['id'].'/approve')->assertOk()->assertJsonPath('business.posted_to_channel', true);

    $post = collect(sentMessages())->first(fn ($m) => $m['chat_id'] === '@habesha_channel');
    expect($post['text'])->toContain('Lalibela Restaurant')->toContain('Al Karama')->toContain('📞 +971501234567')
        ->and(collect($post['reply_markup']['inline_keyboard'][0])->pluck('url')->first())->toBe('https://habesha.example/am/directory/'.$created['slug']);
    expect(lastTo(9010)['text'])->toContain('is approved and now live')->toContain('https://habesha.example/en/directory/'.$created['slug']);

    $this->getJson('/api/public/businesses/'.$created['slug'])->assertOk()
        ->assertJsonPath('business.name', 'Lalibela Restaurant')->assertJsonPath('business.owner_managed', true)
        ->assertJsonPath('business.hours', 'Every day 10:00–23:00');
});

test('a business already listed by the community is refused, with the advice to claim it', function (): void {
    Business::query()->create(listing(['phone' => null, 'whatsapp' => null, 'telegram' => null, 'languages' => null]) + ['status' => 'approved']);
    $token = ownerSignIn(9011);

    asOwner($token)->postJson('/api/owner/businesses', listing(['name' => 'lalibela restaurant']))
        ->assertStatus(422)->assertJsonPath('errors.name.0', 'This business is already in the directory. Open its page and claim it instead.');
});

test('editing a listing in review replaces it; editing a live one waits for review while the live version stays', function (): void {
    $token = ownerSignIn(9012);
    $id = asOwner($token)->postJson('/api/owner/businesses', listing())->json('business.id');

    asOwner($token)->putJson("/api/owner/businesses/{$id}", listing(['description' => 'Now with breakfast.']))->assertOk()
        ->assertJsonPath('business.status', 'pending')->assertJsonPath('business.description', 'Now with breakfast.');

    asAdmin()->postJson("/api/admin/businesses/{$id}/approve")->assertOk();

    $edited = asOwner($token)->putJson("/api/owner/businesses/{$id}", listing(['description' => 'Now open until midnight.', 'hours' => 'Every day 10:00–24:00']))
        ->assertOk()->json('business');
    expect($edited['status'])->toBe('approved')->and($edited['changes_in_review'])->toBeTrue()
        ->and($edited['pending_changes'])->toBe(['description' => 'Now open until midnight.', 'hours' => 'Every day 10:00–24:00']);

    $slug = Business::query()->find($id)->slug;
    $this->getJson('/api/public/businesses/'.$slug)->assertJsonPath('business.description', 'Now with breakfast.');

    asAdmin()->getJson('/api/admin/businesses?status=changes')->assertJsonPath('data.0.id', $id);
    asAdmin()->postJson("/api/admin/businesses/{$id}/changes/approve")->assertOk();

    $this->getJson('/api/public/businesses/'.$slug)->assertJsonPath('business.description', 'Now open until midnight.');
    expect(lastTo(9012)['text'])->toContain('Your changes to <b>Lalibela Restaurant</b> are approved')
        ->and(collect(telegramCalls('editMessageText'))->last()['text'])->toContain('Now open until midnight.');

    asOwner($token)->putJson("/api/owner/businesses/{$id}", listing(['name' => 'Lalibela Kitchen', 'description' => 'Now open until midnight.', 'hours' => 'Every day 10:00–24:00']))->assertOk();
    asAdmin()->postJson("/api/admin/businesses/{$id}/changes/reject", ['note' => 'Keep the registered name.'])->assertOk();
    expect(Business::query()->find($id)->name)->toBe('Lalibela Restaurant')
        ->and(Business::query()->find($id)->pending_changes)->toBeNull()
        ->and(lastTo(9012)['text'])->toContain('Keep the registered name.');
});

test('a photo is re-encoded, previewed through a signed link, and public only once approved', function (): void {
    $token = ownerSignIn(9013);
    $id = asOwner($token)->postJson('/api/owner/businesses', listing())->json('business.id');

    asOwner($token)->post("/api/owner/businesses/{$id}/photo", ['photo' => UploadedFile::fake()->createWithContent('front.jpg', 'not an image')], ['Accept' => 'application/json'])
        ->assertStatus(422);
    asOwner($token)->post("/api/owner/businesses/{$id}/photo", ['photo' => UploadedFile::fake()->createWithContent('tiny.jpg', jpegBytes(120, 90))], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('errors.photo.0', 'The photo is too small. Use one at least 400 pixels wide.');

    $business = asOwner($token)->post("/api/owner/businesses/{$id}/photo", ['photo' => UploadedFile::fake()->createWithContent('front.jpg', jpegBytes(2400, 1600))], ['Accept' => 'application/json'])
        ->assertOk()->json('business');
    expect($business['photo'])->toBeNull()->and($business['pending_photo'])->toContain('/api/public/photos/')->toContain('signature=');

    $photo = Business::query()->find($id)->photos()->sole();
    expect($photo->approved)->toBeFalse()->and($photo->width)->toBe(1600)->and($photo->height)->toBe(1067);

    $preview = parse_url($business['pending_photo']);
    $this->get($preview['path'].'?'.$preview['query'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get($preview['path'])->assertForbidden();
    $this->get('/api/public/photos/'.$photo->id)->assertNotFound();

    asAdmin()->postJson("/api/admin/businesses/{$id}/approve")->assertOk();

    $public = $this->getJson('/api/public/businesses/'.Business::query()->find($id)->slug)->json('business.photo');
    $this->get(parse_url($public, PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    // A small file that claims a huge picture is refused before it is decoded.
    $huge = imagecreatetruecolor(1, 1);
    ob_start();
    imagepng($huge);
    $png = (string) ob_get_clean();
    $png = substr_replace($png, pack('NN', 30000, 30000), 16, 8); // IHDR width and height
    asOwner($token)->post("/api/owner/businesses/{$id}/photo", ['photo' => UploadedFile::fake()->createWithContent('bomb.png', $png)], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('errors.photo.0', 'The photo is too large. Use one under 24 megapixels.');
});

test('listing a hidden business again does not publish the owner\'s queued photo; a moderator\'s edit drops the same queued change', function (): void {
    $token = ownerSignIn(9019);
    $id = asOwner($token)->postJson('/api/owner/businesses', listing())->json('business.id');
    asAdmin()->postJson("/api/admin/businesses/{$id}/approve")->assertOk();
    asAdmin()->postJson("/api/admin/businesses/{$id}/hide", ['note' => 'Checking a complaint'])->assertOk();

    asOwner($token)->post("/api/owner/businesses/{$id}/photo", ['photo' => UploadedFile::fake()->createWithContent('new.jpg', jpegBytes())], ['Accept' => 'application/json'])->assertOk();
    asOwner($token)->putJson("/api/owner/businesses/{$id}", listing(['hours' => 'Closed on Mondays', 'description' => 'Owner text']))->assertOk();
    asAdmin()->postJson("/api/admin/businesses/{$id}/approve")->assertOk();

    $business = Business::query()->find($id);
    expect($business->photos()->where('approved', true)->count())->toBe(0)
        ->and($business->pending_changes)->toBe(['description' => 'Owner text', 'hours' => 'Closed on Mondays']);

    asAdmin()->putJson("/api/admin/businesses/{$id}", ['hours' => 'Every day 09:00–21:00'])->assertOk();
    expect($business->fresh()->pending_changes)->toBe(['description' => 'Owner text']);
});

test('a rejected listing sent again counts toward the listings waiting for review', function (): void {
    $token = ownerSignIn(9020);
    $ids = collect(['One', 'Two', 'Three', 'Four'])->map(function (string $name, int $i) use ($token) {
        if ($i === 3) {
            return null;
        }

        return asOwner($token)->postJson('/api/owner/businesses', listing(['name' => "Place {$name}"]))->json('business.id');
    })->filter()->values();
    asAdmin()->postJson("/api/admin/businesses/{$ids[0]}/reject", ['note' => 'Unclear'])->assertOk();
    asOwner($token)->postJson('/api/owner/businesses', listing(['name' => 'Place Four']))->assertCreated();

    asOwner($token)->putJson("/api/owner/businesses/{$ids[0]}", listing(['name' => 'Place One']))->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'You have 3 listings waiting for review. Send this one again once they are checked.');
});

test('claiming a listing the community added: the moderator approves and the owner can manage it', function (): void {
    $business = Business::query()->create(listing(['phone' => null, 'whatsapp' => null, 'telegram' => null, 'languages' => null]) + ['status' => 'approved']);
    $token = ownerSignIn(9014);
    $rival = ownerSignIn(9015);

    asOwner($token)->postJson("/api/owner/businesses/{$business->id}/claim", ['note' => 'I am the owner; my trade licence is in my name.'])->assertCreated();
    asOwner($token)->postJson("/api/owner/businesses/{$business->id}/claim")->assertStatus(422);
    asOwner($rival)->postJson("/api/owner/businesses/{$business->id}/claim")->assertCreated();
    asOwner($token)->putJson("/api/owner/businesses/{$business->id}", listing())->assertNotFound();

    $claims = asAdmin()->getJson('/api/admin/business-claims')->assertOk()->json('data');
    expect($claims)->toHaveCount(2)->and($claims[0]['note'])->toContain('trade licence');

    asAdmin()->postJson('/api/admin/business-claims/'.$claims[0]['id'].'/approve')->assertOk();
    expect($business->fresh()->owner_id)->toBe(BusinessOwner::query()->where('telegram_user_id', 9014)->value('id'))
        ->and(BusinessClaim::query()->find($claims[1]['id'])->status)->toBe('rejected')
        ->and(lastTo(9014)['text'])->toContain('is now yours to manage');

    asOwner($token)->putJson("/api/owner/businesses/{$business->id}", listing(['description' => 'Updated by the owner.']))->assertOk()
        ->assertJsonPath('business.changes_in_review', true);
});

test('owners only see and change their own listings, within the limits', function (): void {
    $token = ownerSignIn(9016);
    $other = ownerSignIn(9017);
    $id = asOwner($token)->postJson('/api/owner/businesses', listing())->json('business.id');

    asOwner($other)->getJson("/api/owner/businesses/{$id}")->assertNotFound();
    asOwner($other)->deleteJson("/api/owner/businesses/{$id}")->assertNotFound();
    asOwner($other)->getJson('/api/owner/businesses')->assertJsonCount(0, 'businesses');

    asOwner($token)->postJson('/api/owner/businesses', listing(['name' => 'Bad', 'website' => 'javascript:alert(1)', 'languages' => ['xx']]))
        ->assertStatus(422)->assertJsonValidationErrors(['website', 'languages.0']);

    asOwner($token)->postJson('/api/owner/businesses', listing(['name' => 'Second Place']))->assertCreated();
    asOwner($token)->postJson('/api/owner/businesses', listing(['name' => 'Third Place']))->assertCreated();
    asOwner($token)->postJson('/api/owner/businesses', listing(['name' => 'Fourth Place']))->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'You have 3 listings waiting for review. Add more once they are checked.');
});

test('an owner withdraws a listing in review, or takes a live one down, which removes the channel post', function (): void {
    $token = ownerSignIn(9018);
    $pending = asOwner($token)->postJson('/api/owner/businesses', listing())->json('business.id');
    asOwner($token)->deleteJson("/api/owner/businesses/{$pending}")->assertOk()->assertJsonPath('deleted', true);
    expect(Business::query()->find($pending))->toBeNull();

    $live = asOwner($token)->postJson('/api/owner/businesses', listing(['name' => 'Habesha Salon', 'category' => 'salon']))->json('business.id');
    asAdmin()->postJson("/api/admin/businesses/{$live}/approve")->assertOk();
    asOwner($token)->deleteJson("/api/owner/businesses/{$live}")->assertOk()->assertJsonPath('business.status', 'hidden');

    expect(telegramCalls('deleteMessage'))->toHaveCount(1)->and(Business::query()->find($live)->channel_message_id)->toBeNull();
    $this->getJson('/api/public/businesses/'.Business::query()->find($live)->slug)->assertNotFound();
});

test('rejecting a live listing takes its channel post down too', function (): void {
    $token = ownerSignIn(9030);
    $id = asOwner($token)->postJson('/api/owner/businesses', listing())->json('business.id');
    asAdmin()->postJson("/api/admin/businesses/{$id}/approve")->assertOk();
    expect(Business::query()->find($id)->channel_message_id)->not->toBeNull();

    asAdmin()->postJson("/api/admin/businesses/{$id}/reject", ['note' => 'Closed for good'])->assertOk();

    expect(telegramCalls('deleteMessage'))->toHaveCount(1)->and(Business::query()->find($id)->channel_message_id)->toBeNull();
});

test('owners read the API messages in their own language', function (): void {
    $token = ownerSignIn(9031);

    asOwner($token)->withHeader('X-Locale', 'am')->postJson('/api/owner/businesses', listing(['name' => '', 'phone' => 'call me']))
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'የንግዱ ስም ያስፈልጋል።');
    asOwner($token)->withHeader('X-Locale', 'ti')->postJson('/api/owner/businesses', listing(['phone' => 'call me']))
        ->assertStatus(422)->assertJsonPath('errors.phone.0', 'እዚ ቁጽሪ ስልኪ ኣይመስልን።');
    // (Test headers persist between requests, so the default is checked with none at all.)
    $this->flushHeaders();
    asOwner($token)->postJson('/api/owner/businesses', listing(['phone' => 'call me']))
        ->assertStatus(422)->assertJsonPath('errors.phone.0', 'That does not look like a phone number.');
});

test('a card can ask for a narrower copy of a listing photo', function (): void {
    $token = ownerSignIn(9032);
    $id = asOwner($token)->postJson('/api/owner/businesses', listing())->json('business.id');
    asOwner($token)->post("/api/owner/businesses/{$id}/photo", ['photo' => UploadedFile::fake()->createWithContent('front.jpg', jpegBytes(2400, 1600))], ['Accept' => 'application/json'])->assertOk();
    asAdmin()->postJson("/api/admin/businesses/{$id}/approve")->assertOk();

    $url = parse_url($this->getJson('/api/public/businesses/'.Business::query()->find($id)->slug)->json('business.photo'));
    $full = getimagesizefromstring($this->get($url['path'].'?'.$url['query'])->assertOk()->getContent());
    $thumb = getimagesizefromstring($this->get($url['path'].'?'.$url['query'].'&w=480')->assertOk()->getContent());
    $odd = getimagesizefromstring($this->get($url['path'].'?'.$url['query'].'&w=123')->assertOk()->getContent());

    expect($full[0])->toBe(1600)->and($thumb[0])->toBe(480)->and($odd[0])->toBe(1600);
});
