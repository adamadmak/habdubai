<?php

use App\Bot\BotProfile;
use App\Bot\Understand;
use App\Models\Announcement;
use App\Models\AnnouncementTopic;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\DirectoryCity;
use App\Models\Job;
use App\Models\JobAlert;
use App\Models\JobFilter;
use App\Models\Member;
use App\Models\PlatformSetting;
use App\Models\Question;
use Laravel\Sanctum\PersonalAccessToken;

/** What Telegram hands a Mini App: its fields, signed with a key made from the bot token. */
function launchData(int $userId, array $user = [], ?int $authDate = null, ?string $token = null): string
{
    $token ??= (string) config('services.telegram.bot_token');
    $fields = [
        'auth_date' => (string) ($authDate ?? now()->timestamp),
        'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
        'user' => (string) json_encode(['id' => $userId, 'first_name' => 'Abebe', 'last_name' => 'K', 'username' => 'abebe', 'language_code' => 'en'] + $user),
    ];
    ksort($fields);
    $checked = implode("\n", array_map(fn (string $k, string $v) => $k.'='.$v, array_keys($fields), $fields));
    $fields['hash'] = hash_hmac('sha256', $checked, hash_hmac('sha256', $token, 'WebAppData', true));

    return http_build_query($fields);
}

function homeJob(array $attributes = []): Job
{
    static $n = 0;
    $n++;

    return Job::query()->create($attributes + [
        'url' => 'https://jobs.example.com/view/'.$n, 'url_key' => 'k'.$n, 'fingerprint' => 'f'.$n, 'category' => 'driver', 'emirate' => 'sharjah',
        'title' => 'Light vehicle driver '.$n, 'company' => 'Fast Logistics', 'summary_en' => 'A driving job.', 'status' => 'published', 'published_at' => now(), 'scam_score' => 0,
    ]);
}

function homePlace(array $attributes = []): Business
{
    static $n = 0;
    $n++;

    return Business::query()->create($attributes + [
        'city_id' => DirectoryCity::query()->where('key', 'dubai')->value('id'), 'category' => 'restaurant', 'name' => 'Place '.$n, 'area' => 'Deira', 'status' => 'approved',
    ]);
}

function tapHome(int $userId, string $data): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'callback_query' => ['id' => 'cb'.nextUpdateId(), 'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'X'], 'message' => ['message_id' => 77, 'chat' => ['id' => $userId, 'type' => 'private']], 'data' => $data],
    ])->assertOk();
}

/** What the bot answers to this, and it must answer: the last message from before does not count. */
function answerTo(int $userId, string $text): string
{
    $before = count(sentMessages());
    say($userId, $text);
    $after = sentMessages();
    expect(count($after))->toBeGreaterThan($before, "The bot said nothing to: {$text}");

    return end($after)['text'];
}

/** The same for a button under a message. */
function answerToTap(int $userId, string $data): string
{
    $before = count(sentMessages());
    tapHome($userId, $data);
    $after = sentMessages();
    expect(count($after))->toBeGreaterThan($before, "The bot said nothing to the button: {$data}");

    return end($after)['text'];
}

/** The ask-the-assistant button on the last message. */
function askButton(): ?string
{
    return collect(buttonsOf(lastMessage()))->first(fn (string $data) => str_starts_with($data, 'h:ai:'));
}

/** Every button on a message, as Telegram receives them. */
function inlineButtons(array $message): array
{
    return collect($message['reply_markup']['inline_keyboard'] ?? [])->flatten(1)->all();
}

// ---- the website inside Telegram ----------------------------------------------------------

test('opening the website inside Telegram signs the member in, with no code to type', function (): void {
    $member = joinMember(61);

    $response = $this->postJson('/api/owner/login/webapp', ['init_data' => launchData(61)])->assertOk()->assertJsonPath('status', 'approved')->json();

    $owner = BusinessOwner::query()->where('telegram_user_id', 61)->sole();
    expect($owner->member_id)->toBe($member->id)->and($owner->display_name)->toBe('Abebe K')
        ->and($response['owner']['member'])->toBeTrue();

    app('auth')->forgetGuards();
    $this->withToken($response['token'])->getJson('/api/owner/home')->assertOk()->assertJsonPath('member.name', 'Abebe K');
});

test('someone who has not joined still gets an account, and sees the way to join', function (): void {
    $token = $this->postJson('/api/owner/login/webapp', ['init_data' => launchData(62)])->assertOk()->json('token');

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/owner/home')->assertOk()->assertJsonPath('member', null);
});

test('launch data that is not exactly what Telegram signed gets nobody in', function (): void {
    $good = launchData(63);

    $tampered = str_replace('%22id%22%3A63', '%22id%22%3A64', $good);
    $this->postJson('/api/owner/login/webapp', ['init_data' => $tampered])->assertUnauthorized()->assertJsonPath('status', 'invalid');

    // Signed with another bot's token.
    $this->postJson('/api/owner/login/webapp', ['init_data' => launchData(63, token: '999:another-bots-token-entirely')])->assertUnauthorized();

    // An old launch, or one from the future.
    $this->postJson('/api/owner/login/webapp', ['init_data' => launchData(63, authDate: now()->subHours(2)->timestamp)])->assertUnauthorized();
    $this->postJson('/api/owner/login/webapp', ['init_data' => launchData(63, authDate: now()->addHours(2)->timestamp)])->assertUnauthorized();

    // Nothing signed at all.
    $this->postJson('/api/owner/login/webapp', ['init_data' => 'user=%7B%22id%22%3A63%7D&auth_date='.now()->timestamp])->assertUnauthorized();

    expect(BusinessOwner::query()->count())->toBe(0);
});

test('opening it again keeps the session; another person on the same device gets their own', function (): void {
    $first = $this->postJson('/api/owner/login/webapp', ['init_data' => launchData(65)])->json('token');
    expect(PersonalAccessToken::query()->count())->toBe(1);

    app('auth')->forgetGuards();
    $this->withToken($first)->postJson('/api/owner/login/webapp', ['init_data' => launchData(65)])->assertOk()->assertJsonPath('status', 'same');
    expect(PersonalAccessToken::query()->count())->toBe(1);

    app('auth')->forgetGuards();
    $this->withToken($first)->postJson('/api/owner/login/webapp', ['init_data' => launchData(66)])->assertOk()->assertJsonPath('status', 'approved');
    expect(PersonalAccessToken::query()->count())->toBe(2);
    $this->flushHeaders();
});

test('a banned member cannot sign in this way either', function (): void {
    joinMember(67)->forceFill(['banned_at' => now()])->save();

    $this->postJson('/api/owner/login/webapp', ['init_data' => launchData(67)])->assertForbidden()->assertJsonPath('status', 'blocked');
});

// ---- the welcome ---------------------------------------------------------------------------

test('after joining, the website opens inside Telegram and the Open button and commands are set for this member', function (): void {
    PlatformSetting::put('site_url', 'https://habesha.example');
    PlatformSetting::put('telegram_channel_link', 'https://t.me/habesha_dubai');

    say(71, '/start');
    say(71, 'አማርኛ');
    shareContact(71, '+971501230071');
    say(71, 'አበበ ከበደ');
    say(71, 'ዱባይ');
    say(71, 'ሹፌር');
    say(71, '✅ ጨርሻለሁ');
    say(71, '✅ እስማማለሁ');

    $card = collect(telegramCalls('sendPhoto'))->last();
    $buttons = collect($card['reply_markup']['inline_keyboard'])->flatten(1);
    expect($buttons->first()['text'])->toBe('🌐 ድረ-ገጹን ይጎብኙ')
        ->and($buttons->first()['web_app']['url'])->toBe('https://habesha.example/am')
        ->and($buttons->firstWhere('web_app.url', 'https://habesha.example/am/account'))->not->toBeNull()
        ->and($buttons->firstWhere('url', 'https://t.me/habesha_dubai'))->not->toBeNull();

    $menu = collect(telegramCalls('setChatMenuButton'))->last();
    expect($menu['chat_id'])->toBe(71)
        ->and($menu['menu_button'])->toBe(['type' => 'web_app', 'text' => 'ክፈት', 'web_app' => ['url' => 'https://habesha.example/am']]);

    $commands = collect(telegramCalls('setMyCommands'))->last();
    expect($commands['scope'])->toBe(['type' => 'chat', 'chat_id' => 71])
        ->and(collect($commands['commands'])->firstWhere('command', 'jobs')['description'])->toBe('ሥራዎችና የሥራ ማንቂያዎች');
});

test('without a public https website the same buttons are ordinary links, and no Open button is set', function (): void {
    PlatformSetting::put('site_url', 'http://localhost:3001');

    joinMember(72);

    $welcome = collect(sentMessages())->first(fn (array $m) => str_contains($m['text'], 'You are in'));
    expect(collect(inlineButtons($welcome))->first()['url'])->toBe('http://localhost:3001/en')
        ->and(collect(inlineButtons($welcome))->pluck('web_app')->filter()->all())->toBe([])
        ->and(telegramCalls('setChatMenuButton'))->toBe([])
        ->and(telegramCalls('sendPhoto'))->toBe([]);
});

test('job alerts start from the work a member named when joining, without ever opening /jobs', function (): void {
    $member = joinMember(73);

    expect(implode("\n", textsTo(73)))->toContain('Job alerts are on for Driver')
        ->and(JobFilter::query()->where('member_id', $member->id)->sole()->categories)->toBe(['driver'])
        ->and(JobFilter::query()->where('member_id', $member->id)->sole()->alerts_on)->toBeTrue();

    $job = homeJob(['category' => 'driver']);
    app(\App\Jobs\Pipeline\JobAlerts::class)->queueFor($job);
    expect(JobAlert::query()->where('member_id', $member->id)->where('job_id', $job->id)->exists())->toBeTrue();

    $other = homeJob(['category' => 'nursing']);
    app(\App\Jobs\Pipeline\JobAlerts::class)->queueFor($other);
    expect(JobAlert::query()->where('job_id', $other->id)->exists())->toBeFalse();
});

test('joining shows how far along it is, at every step', function (): void {
    say(74, '/start');
    say(74, 'English');
    expect(lastText())->toStartWith('▰▱▱▱▱ <b>1/5</b>');
    shareContact(74, '+971501230074');
    expect(lastText())->toStartWith('▰▰▱▱▱ <b>2/5</b>');
    say(74, 'Abebe K');
    expect(lastText())->toStartWith('▰▰▰▱▱ <b>3/5</b>');
    say(74, 'Dubai');
    expect(lastText())->toStartWith('▰▰▰▰▱ <b>4/5</b>');
    say(74, 'Driver');
    say(74, '✅ Done');
    expect(lastText())->toStartWith('▰▰▰▰▰ <b>5/5</b>');
});

// ---- the home on /start ---------------------------------------------------------------------

test('/start shows what is new for this member: their jobs, their deals, their answers, the next event', function (): void {
    PlatformSetting::put('site_url', 'https://habesha.example');
    $member = joinMember(75);

    homeJob(['category' => 'driver']);
    homeJob(['category' => 'driver']);
    homeJob(['category' => 'nursing']);
    homeJob(['category' => 'driver', 'published_at' => now()->subDays(10)]);
    $question = Question::query()->create(['member_id' => $member->id, 'category' => 'visa', 'title' => 'Can I change employer?', 'body' => 'Still on probation.', 'status' => 'open', 'last_activity_at' => now()]);
    $question->replies()->create(['member_id' => $member->id, 'telegram_message_id' => 1, 'text' => 'Yes, after the first year.', 'created_at' => now()]);
    Announcement::query()->create(['topic_id' => AnnouncementTopic::query()->value('id'), 'text_en' => 'Timket celebration in Deira', 'text_am' => 'ጥምቀት በዴራ', 'text_ti' => 'ጥምቀት ኣብ ዴራ', 'text_om' => 'Ayyaana Timqataa Deira keessatti', 'status' => 'published', 'event_date' => now()->addDays(3)->toDateString(), 'original_language' => 'en', 'published_at' => now()]);

    say(75, '/start');

    $home = lastMessage();
    expect($home['text'])->toContain('What is new for you')->toContain('2 new jobs for you this week')
        ->toContain('1 of your questions have answers')->toContain('Timket celebration in Deira')
        ->and(collect(inlineButtons($home))->first()['web_app']['url'])->toBe('https://habesha.example/en')
        ->and(buttonsOf($home))->toContain('jf:latest')->toContain('h:alerts')->toContain('h:myq')->toContain('h:mine');
});

test('the buttons on the home and the profile do what they say', function (): void {
    joinMember(76);

    expect(answerToTap(76, 'h:alerts'))->toContain('Job alerts');

    expect(answerToTap(76, 'h:myq'))->not->toBe('');
    expect(answerToTap(76, 'h:mine'))->not->toBe('');

    expect(answerToTap(76, 'h:safe'))->toContain('Help and safety');

    say(76, '/profile');
    expect(buttonsOf(lastMessage()))->toContain('h:alerts')->toContain('h:lang');

    tapHome(76, 'h:lang');
    expect(lastText())->toBe('Choose your language.');
    expect(\App\Models\Conversation::query()->where('telegram_user_id', 76)->value('state'))->toBe('set_language');
});

// ---- what a member types --------------------------------------------------------------------

test('typing what you need finds it, in every language', function (string $text, string $finds): void {
    joinMember(77);
    homeJob(['title' => 'Light vehicle driver', 'category' => 'driver', 'emirate' => 'sharjah']);
    homeJob(['title' => 'Mall security guard', 'category' => 'security', 'emirate' => 'dubai']);

    say(77, $text);

    expect(lastText())->toContain($finds);
})->with([
    'english' => ['driver job sharjah', 'Light vehicle driver'],
    'a whole sentence' => ['I am looking for a driver job in Sharjah please', 'Light vehicle driver'],
    'amharic' => ['ሹፌር ሻርጃ', 'Light vehicle driver'],
    'amharic with endings' => ['በዱባይ የጥበቃ ሥራ እፈልጋለሁ', 'Mall security guard'],
    'tigrinya' => ['ሓለዋ ዱባይ', 'Mall security guard'],
    'oromo' => ['konkolaachisaa hojii', 'Light vehicle driver'],
    'just the trade' => ['guard', 'Mall security guard'],
]);

test('a trade with nothing open offers to say when one comes in, and does', function (): void {
    $member = joinMember(78);

    expect(answerTo(78, 'plumber job'))->toContain('nothing is open right now')
        ->and(buttonsOf(lastMessage()))->toContain('h:alert:construction')
        ->and(askButton())->not->toBeNull();

    expect(answerToTap(78, 'h:alert:construction'))->toContain('I will message you')->toContain('Construction')->not->toContain('Only in')
        ->and(JobFilter::query()->where('member_id', $member->id)->sole()->categories)->toBe(['driver', 'construction']);

    $job = homeJob(['category' => 'construction']);
    app(\App\Jobs\Pipeline\JobAlerts::class)->queueFor($job);
    expect(JobAlert::query()->where('member_id', $member->id)->where('job_id', $job->id)->exists())->toBeTrue();

    // A button pressed with something made up changes nothing.
    tapHome(78, 'h:alert:nonsense');
    expect(JobFilter::query()->where('member_id', $member->id)->sole()->categories)->toBe(['driver', 'construction']);
});

test('the promise of an alert says so when the member keeps their alerts to some emirates', function (): void {
    $member = joinMember(87);
    JobFilter::query()->where('member_id', $member->id)->update(['emirates' => json_encode(['sharjah'])]);

    expect(answerToTap(87, 'h:alert:nursing'))->toContain('I will message you')->toContain('Only in Sharjah');
});

test('someone coming back is told the alerts they really have', function (): void {
    $member = joinMember(88);
    $member->forceFill(['left_at' => now(), 'agreed_rules_at' => null])->save();
    JobFilter::query()->where('member_id', $member->id)->update(['alerts_on' => false]);
    $home = app(\App\Bot\Home::class);

    $home->welcome($member->fresh(), $member->chat_id);
    expect(implode("\n", textsTo(88)))->toContain('Job alerts are on for Driver');
    expect(lastText())->not->toContain('Job alerts are on');

    $count = count(sentMessages());
    $home->welcome($member->fresh(), $member->chat_id);
    $new = array_slice(sentMessages(), $count);
    expect(collect($new)->pluck('text')->implode("\n"))->not->toContain('Job alerts are on');
});

test('jobs in an emirate with no trade offer the alert settings, not a promise with no button', function (): void {
    joinMember(89);

    expect(answerTo(89, 'job ajman'))->toContain('nothing is open right now')
        ->and(buttonsOf(lastMessage()))->toContain('h:alerts');
});

test('a word that can mean guarding work or protection offers the safety numbers as well', function (): void {
    joinMember(90);
    homeJob(['title' => 'Mall security guard', 'category' => 'security']);

    expect(answerTo(90, 'ጥበቃ እፈልጋለሁ'))->toContain('Mall security guard')
        ->and(buttonsOf(lastMessage()))->toContain('h:safe');

    // Said as a job, it is a job.
    expect(answerTo(90, 'የጥበቃ ሥራ'))->toContain('Mall security guard')
        ->and(buttonsOf(lastMessage()))->not->toContain('h:safe');
});

test('alerts for all work stay for all work when one trade is asked for', function (): void {
    $member = joinMember(79);
    JobFilter::query()->where('member_id', $member->id)->update(['categories' => json_encode([]), 'alerts_on' => false]);

    tapHome(79, 'h:alert:nursing');

    $filter = JobFilter::query()->where('member_id', $member->id)->sole();
    expect($filter->categories)->toBe([])->and($filter->alerts_on)->toBeTrue();
});

test('typing a kind of place finds places, narrowed by city and area', function (): void {
    joinMember(80);
    $sharjah = DirectoryCity::query()->where('key', 'sharjah')->value('id');
    homePlace(['name' => 'Dukem Restaurant', 'category' => 'restaurant', 'area' => 'Deira']);
    homePlace(['name' => 'Addis Kitchen', 'category' => 'restaurant', 'area' => 'Al Nahda', 'city_id' => $sharjah]);
    homePlace(['name' => 'Selam Cargo', 'category' => 'cargo', 'area' => 'Naif']);
    homePlace(['name' => 'Hidden Place', 'category' => 'restaurant', 'status' => 'pending']);

    say(80, 'restaurant in deira');
    expect(lastText())->toContain('Dukem Restaurant')->not->toContain('Addis Kitchen')->not->toContain('Hidden Place')
        ->and(askButton())->not->toBeNull();

    expect(answerTo(80, 'restaurant sharjah'))->toContain('Addis Kitchen')->not->toContain('Dukem Restaurant');

    expect(answerTo(80, 'እንጀራ'))->toContain('Dukem Restaurant')->toContain('Addis Kitchen');

    expect(answerTo(80, 'where can I find injera?'))->toContain('Dukem Restaurant');

    // An area that matches nothing still shows the kind of place asked for.
    expect(answerTo(80, 'cargo jumeirah'))->toContain('Selam Cargo');

    // The name of a place is enough.
    expect(answerTo(80, 'Dukem'))->toContain('Dukem Restaurant')->not->toContain('Selam Cargo');
});

test('a kind of place nobody has listed yet invites a suggestion', function (): void {
    joinMember(81);

    say(81, 'dentist');

    expect(lastText())->toContain('nothing listed yet')->and(buttonsOf(lastMessage()))->toContain('d:add')
        ->and(askButton())->not->toBeNull();
});

test('trouble goes straight to the safety numbers, and parcels to the goods board', function (): void {
    joinMember(82);

    expect(answerTo(82, 'embassy'))->toContain('Help and safety');
    expect(answerTo(82, 'my employer took my passport'))->toContain('Help and safety');
    expect(answerTo(82, 'ኤምባሲ'))->toContain('Help and safety');
    expect(answerTo(82, 'parcel to addis'))->toContain('Goods board');
});

test('someone in trouble gets the numbers however they say it, even when the assistant cannot answer', function (string $text): void {
    joinMember(91);
    Illuminate\Support\Facades\Cache::put('assistant:'.Member::query()->where('telegram_user_id', 91)->value('id').':'.now()->toDateString(), 9999, now()->addHour());

    say(91, $text);

    expect(collect(textsTo(91))->last(fn (string $t) => str_contains($t, 'Help and safety')))->not->toBeNull();
})->with([
    'a sentence' => ['my employer is beating me, i need the police'],
    'a question' => ['how do i call the police'],
    'amharic question' => ['ፖሊስ ስንት ነው ቁጥሩ'],
    'domestic violence is not domestic work' => ['domestic violence'],
    'he took my passport, in amharic' => ['ፓስፖርቴን ወሰደብኝ'],
    'abused' => ['my boss abused me'],
    'a long story' => ['since last month the family I work for has kept me inside the house and they will not pay me and yesterday they threatened me'],
]);

test('a sentence about trouble goes to the assistant as well as the numbers; a plain word does not', function (): void {
    joinMember(92);

    say(92, 'my employer is beating me, i need the police');
    expect(\App\Models\AssistantMessage::query()->where('role', 'user')->count())->toBe(1);

    say(92, 'police');
    expect(\App\Models\AssistantMessage::query()->where('role', 'user')->count())->toBe(1);
});

test('a real question goes to the assistant, and "not this" hands it what was typed', function (): void {
    joinMember(83);
    homePlace(['name' => 'Dukem Restaurant']);

    say(83, 'How do I change my employer?');
    expect(\App\Models\AssistantMessage::query()->where('role', 'user')->pluck('text')->all())->toBe(['How do I change my employer?']);

    expect(answerTo(83, 'restaurant'))->toContain('Dukem Restaurant')
        ->and(\App\Models\AssistantMessage::query()->where('role', 'user')->count())->toBe(1);
    $first = askButton();

    // A later message does not change what an earlier button asks.
    answerTo(83, 'Dukem');

    tapHome(83, $first);
    expect(\App\Models\AssistantMessage::query()->where('role', 'user')->pluck('text')->all())->toBe(['How do I change my employer?', 'restaurant']);

    // Only once: pressed again, it says what to do instead of doing nothing.
    expect(answerToTap(83, $first))->toContain('Send your question again');
    expect(\App\Models\AssistantMessage::query()->where('role', 'user')->count())->toBe(2);
});

test('saying hello gets the member their home, with no call to the assistant', function (string $text): void {
    joinMember(93);

    expect(answerTo(93, $text))->toContain('What is new for you');
    expect(\App\Models\AssistantMessage::query()->count())->toBe(0);
})->with(['hi', 'Hello!', 'selam', 'ሰላም', 'ጤና ይስጥልኝ', 'good morning', 'akkam']);

test('ordinary talk is not taken for a search', function (string $text): void {
    joinMember(94);
    homePlace(['name' => 'Book Corner', 'category' => 'education']);
    homePlace(['name' => 'Noor Cargo', 'category' => 'cargo']);
    homePlace(['name' => 'Yeshi Kitchen', 'category' => 'restaurant']);
    homeJob(['title' => 'Mall security guard', 'category' => 'security']);

    say(94, $text);

    expect(\App\Models\AssistantMessage::query()->where('role', 'user')->pluck('text')->all())->toBe([$text]);
})->with(['it doesn\'t work', 'work permit', 'passport renewal', 'salary package', 'of course', 'labour office', 'food delivery', 'አስራ አምስት ቀን']);

test('thanks, yes and no get a word back and the menu: no search, no call to the assistant', function (string $text): void {
    joinMember(103);
    homePlace(['name' => 'Book Corner', 'category' => 'education']);
    homePlace(['name' => 'Noor Cargo', 'category' => 'cargo']);
    homePlace(['name' => 'Yeshi Kitchen', 'category' => 'restaurant']);

    expect(answerTo(103, $text))->toContain('Anytime')->not->toContain('Book Corner')->not->toContain('Noor Cargo')->not->toContain('Yeshi Kitchen');
    expect(\App\Models\AssistantMessage::query()->count())->toBe(0);
})->with(['ok', 'no', 'yes', 'thanks', 'thank you very much', 'good job', 'አመሰግናለሁ', 'እሺ', 'galatoomaa']);

test('a bare cry for help gets the safety numbers; help with something else is that something', function (): void {
    joinMember(104);

    expect(answerTo(104, 'help'))->toContain('Help and safety');
    expect(answerTo(104, 'እርዱኝ'))->toContain('Help and safety');
    expect(app(Understand::class)->read('help me find a driver job')->kind)->toBe('jobs');
});

test('a name is found where a word of it starts, not in the middle of one', function (): void {
    joinMember(95);
    homePlace(['name' => 'Book Corner', 'category' => 'education']);
    homePlace(['name' => 'Facebook Shop', 'category' => 'grocery']);

    expect(answerTo(95, 'book'))->toContain('Book Corner')->not->toContain('Facebook Shop');
});

test('what cannot be placed goes to the assistant, and costs nothing until then', function (): void {
    joinMember(84);

    say(84, 'my uncle told me something strange yesterday');

    expect(\App\Models\AssistantMessage::query()->where('role', 'user')->pluck('text')->all())->toBe(['my uncle told me something strange yesterday']);
});

test('reading a message: words that look like others are not mistaken for them', function (string $text, ?string $kind, bool $direct): void {
    $read = app(Understand::class)->read($text);

    expect($read->kind)->toBe($kind)->and($read->direct)->toBe($direct);
})->with([
    'chair is not hair' => ['a good chair', null, true],
    'guards is guard' => ['guards', 'jobs', true],
    'a question stays a question' => ['how much does a driver earn?', 'jobs', false],
    'an amharic question' => ['ሹፌር እንዴት መሆን ይቻላል', 'jobs', false],
    'a long story is for the assistant' => ['my brother works as a driver somewhere near the airport with his friend and cousin', 'jobs', false],
    'six words are still a request' => ['driver job sharjah deira morning shift', 'jobs', true],
    'which job is a question' => ['የትኛው ሥራ ይሻላል?', 'jobs', false],
    'ten is not work' => ['አስራ አምስት', null, true],
    'a greeting is not a clinic' => ['ጤና ይስጥልኝ', 'hello', true],
    'of course is not a course' => ['of course', null, true],
    'a course is' => ['course', 'places', true],
    'looking for work, in amharic' => ['ሥራ እየፈለግኩ ነው', 'jobs', true],
    'looking for work, the short way' => ['ሥራ ፈልጌ ነው', 'jobs', true],
    'a new job' => ['አዲስ ስራ', 'jobs', true],
    'looking for work, in tigrinya' => ['ስራሕ ደልየ', 'jobs', true],
    'looking for work, in oromo' => ['hojii nan barbaada', 'jobs', true],
    'a barber is a place' => ['barber', 'places', true],
    'a barber job is work' => ['barber job', 'jobs', true],
    'he holds my passport, in amharic' => ['ፓስፖርቴን ያዙብኝ', 'safety', true],
    'renewing a passport is no trouble' => ['ፓስፖርት ማደስ', null, true],
    'nothing to place' => ['good morning everyone', null, true],
]);

// ---- how the bot presents itself ---------------------------------------------------------------

test('the first update after a change sets the commands, description and Open button; the next does not', function (): void {
    PlatformSetting::put('site_url', 'https://habesha.example');

    say(85, '/help');

    $commands = collect(telegramCalls('setMyCommands'));
    expect($commands->pluck('language_code')->filter()->values()->all())->toBe(['am', 'ti', 'om', 'en'])
        ->and($commands->first()['scope'])->toBe(['type' => 'all_private_chats'])
        ->and(collect($commands->first()['commands'])->pluck('command')->all())->toBe(['jobs', 'goods', 'ask', 'directory', 'safe', 'profile', 'language', 'help'])
        ->and(collect(telegramCalls('setMyDescription'))->last()['description'])->toContain('free community')
        ->and(collect(telegramCalls('setMyShortDescription'))->last()['short_description'])->toContain('spare kilos')
        ->and(collect(telegramCalls('setChatMenuButton'))->last()['menu_button']['web_app']['url'])->toBe('https://habesha.example');

    $before = count(telegramCalls('setMyCommands'));
    say(85, '/help');
    expect(count(telegramCalls('setMyCommands')))->toBe($before);

    // A new website address brings it up to date.
    PlatformSetting::put('site_url', 'https://habesha-dubai.example');
    \App\Bot\BotMessages::flush();
    say(85, '/help');
    expect(collect(telegramCalls('setChatMenuButton'))->last()['menu_button']['web_app']['url'])->toBe('https://habesha-dubai.example');
});

test('every description fits Telegram\'s limits, in every language', function (): void {
    $messages = app(\App\Bot\BotMessages::class);

    foreach (\App\Bot\Choices::LANGUAGES as $language) {
        expect(mb_strlen($messages->text('bot_description', $language)))->toBeLessThanOrEqual(512)
            ->and(mb_strlen($messages->text('bot_short_description', $language)))->toBeLessThanOrEqual(120)
            ->and(mb_strlen($messages->text('btn_open_site', $language)))->toBeLessThanOrEqual(64);
        foreach (app(BotProfile::class)->commands($language) as $command) {
            expect(mb_strlen($command['description']))->toBeLessThanOrEqual(256)->toBeGreaterThan(0)
                ->and($command['command'])->toMatch('/^[a-z0-9_]{1,32}$/');
        }
    }
});

test('if Telegram refuses, the message is still answered, nothing repeats on every message, and a later one tries again', function (): void {
    TelegramFake::$errors['setMyCommands'] = [500, 'Internal Server Error'];
    expect(answerTo(86, '/help'))->not->toBe('');
    expect(PlatformSetting::valueFor(BotProfile::SYNCED_KEY))->toBeNull();

    $tries = count(telegramCalls('setMyCommands'));
    expect($tries)->toBeGreaterThan(0);
    expect(answerTo(86, '/help'))->not->toBe('');
    expect(count(telegramCalls('setMyCommands')))->toBe($tries);

    TelegramFake::$errors = [];
    $this->travel(6)->minutes();
    say(86, '/help');
    expect(PlatformSetting::valueFor(BotProfile::SYNCED_KEY))->not->toBeNull();
});

test('the Open button refused at the end means the whole profile is tried again', function (): void {
    PlatformSetting::put('site_url', 'https://habesha.example');
    TelegramFake::$errors['setChatMenuButton'] = [429, 'Too Many Requests'];

    say(96, '/help');
    expect(PlatformSetting::valueFor(BotProfile::SYNCED_KEY))->toBeNull();

    TelegramFake::$errors = [];
    $this->travel(6)->minutes();
    say(96, '/help');
    expect(PlatformSetting::valueFor(BotProfile::SYNCED_KEY))->not->toBeNull()
        ->and(collect(telegramCalls('setChatMenuButton'))->last()['menu_button']['web_app']['url'])->toBe('https://habesha.example');
});

test('a new address reaches each member\'s own Open button too, a few at a time', function (): void {
    PlatformSetting::put('site_url', 'https://habesha.example');
    $members = [joinMember(97, '+971501230097'), joinMember(98, '+971501230098'), joinMember(99, '+971501230099')];
    $left = joinMember(100, '+971501230100');
    $left->forceFill(['left_at' => now()])->save();
    say(97, '/help');

    PlatformSetting::put('site_url', 'https://habesha-dubai.example');
    say(97, '/help');
    expect(PlatformSetting::valueFor(BotProfile::CHATS_CURSOR_KEY))->toBe('0');

    $before = count(telegramCalls('setChatMenuButton'));
    expect(app(\App\Bot\Home::class)->refreshChats(2))->toBe(2);
    expect(app(\App\Bot\Home::class)->refreshChats(2))->toBe(1);
    expect(PlatformSetting::valueFor(BotProfile::CHATS_CURSOR_KEY))->toBeNull();
    expect(app(\App\Bot\Home::class)->refreshChats(2))->toBe(0);

    $calls = collect(array_slice(telegramCalls('setChatMenuButton'), $before));
    expect($calls->pluck('chat_id')->all())->toBe([97, 98, 99])
        ->and($calls->first()['menu_button']['web_app']['url'])->toBe('https://habesha-dubai.example/en');
});

test('a session cookie that holds rubbish is no session, not a database error', function (): void {
    expect(app(\App\Directory\OwnerLogin::class)->webApp(['id' => 101, 'first_name' => 'A'], 'abc|def', null)['status'])->toBe('approved');
});

test('Telegram has the whole answer at once, whatever the handler then does', function (): void {
    $this->postJson('/api/telegram/webhook/'.webhookSecret(), telegramMessage(102, ['text' => '/help']))
        ->assertOk()->assertHeader('Content-Length', '2');
});
