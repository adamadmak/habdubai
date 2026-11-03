<?php

use App\Bot\BotMessages;
use App\Jobs\Pipeline\JobAlerts;
use App\Models\Business;
use App\Models\EmployerReport;
use App\Models\FaqEntry;
use App\Models\GroupReport;
use App\Models\JobAlert;
use App\Models\JobFilter;
use App\Models\Member;
use App\Models\PlatformSetting;
use App\Models\TelegramUpdate;
use App\Services\TelegramBotService;
use App\Support\RuntimeEnv;
use App\Support\TelegramHtml;
use App\Support\TelegramText;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/** Press an inline button in the bot's private chat. */
function tapButton(int $userId, string $data, int $messageId = 55): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'callback_query' => [
            'id' => 'cb'.nextUpdateId(),
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Abebe', 'language_code' => 'en'],
            'message' => ['message_id' => $messageId, 'chat' => ['id' => $userId, 'type' => 'private']],
            'data' => $data,
        ],
    ])->assertOk();
}

function sendSticker(int $userId): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), telegramMessage($userId, ['sticker' => ['file_id' => 'CAACAgI', 'emoji' => '👍']]))->assertOk();
}

function enLabel(string $key): string
{
    return app(BotMessages::class)->button($key, 'en');
}

// ---- Telegram's limits -------------------------------------------------------------------

test('a message longer than Telegram allows goes as several valid messages, the buttons on the last', function (): void {
    $long = implode("\n", array_map(fn (int $i) => '<b>Line '.$i.' &amp; more</b> '.str_repeat('ሰላም ', 30).'<a href="https://example.com/'.$i.'">link</a>', range(1, 80)));
    $buttons = ['inline_keyboard' => [[['text' => 'Ask', 'callback_data' => 'q:fromai']]]];

    app(TelegramBotService::class)->sendText(555, $long, $buttons);

    $sent = sentMessages();
    expect(count($sent))->toBeGreaterThan(1);
    foreach ($sent as $i => $message) {
        expect(TelegramText::length($message['text']))->toBeLessThanOrEqual(TelegramText::MAX_TEXT)
            ->and(TelegramHtml::problem($message['text']))->toBeNull()
            ->and($message['reply_markup'])->toBe($i === count($sent) - 1 ? $buttons : null);
    }
    expect(implode("\n", array_column($sent, 'text')))->toContain('Line 1 &amp; more')->toContain('Line 80 &amp; more');
});

test('a long FAQ topic is shortened at a line with its tags closed, never cut inside a link', function (): void {
    joinMember(9601, '+971500009601');
    foreach (range(1, 10) as $i) {
        FaqEntry::query()->create(['category' => 'visa', 'q_en' => 'Visa question '.$i.'?', 'a_en' => str_repeat('A long checked answer & its details. ', 25), 'source_url' => 'https://u.ae/en/visa-'.$i, 'active' => true]);
    }

    tapButton(9601, 'f:cat:visa');

    $text = collect(telegramCalls('editMessageText'))->last()['text'];
    expect(TelegramText::length($text))->toBeLessThanOrEqual(4000)
        ->and(TelegramHtml::problem($text))->toBeNull()
        ->and($text)->toContain('Visa question 1?')->toContain('…');
});

test('a message Telegram refuses for good is not run again, and the webhook still answers', function (): void {
    joinMember(9602, '+971500009602');
    TelegramFake::$errors['sendMessage'] = [400, 'Bad Request: message is too long'];
    $before = count(telegramCalls('sendMessage'));

    say(9602, '/help')->assertOk();

    $update = TelegramUpdate::query()->latest('id')->first();
    expect($update->processed_at)->toBeNull()
        ->and($update->error)->toContain('message is too long')
        ->and($update->attempts)->toBe(TelegramUpdate::MAX_ATTEMPTS)
        // Never run again, so its content is gone at once; only its id and kind stay.
        ->and($update->payload)->toMatchArray(['kind' => 'message', 'redacted' => true])
        ->and(json_encode($update->payload))->not->toContain('/help')
        ->and(count(telegramCalls('sendMessage')) - $before)->toBe(1); // no retry on a 400

    TelegramFake::$errors = [];
    $this->travel(5)->minutes();
    $this->artisan('bot:updates-chores')->assertSuccessful();
    expect($update->fresh()->processed_at)->toBeNull()->and($update->fresh()->attempts)->toBe(TelegramUpdate::MAX_ATTEMPTS);
});

test('job alerts for a member who blocked the bot are dropped instead of tried every five minutes', function (): void {
    fakeClassifier();
    $member = joinMember(9603, '+971500009603');
    JobFilter::forMember($member);
    ingest(['title' => 'Driver for a blocked member']);
    expect(JobAlert::query()->where('member_id', $member->id)->whereNull('sent_at')->count())->toBe(1);

    // Telegram having trouble: the alert waits for the next run.
    TelegramFake::$errors['sendMessage'] = [502, 'Bad Gateway'];
    expect(app(JobAlerts::class)->sendDue())->toBe(0)
        ->and(JobAlert::query()->where('member_id', $member->id)->whereNull('sent_at')->count())->toBe(1);

    // The member blocked the bot: nothing will ever arrive, so nothing waits.
    TelegramFake::$errors['sendMessage'] = [403, 'Forbidden: bot was blocked by the user'];
    expect(app(JobAlerts::class)->sendDue())->toBe(0)
        ->and(JobAlert::query()->where('member_id', $member->id)->whereNull('sent_at')->count())->toBe(0);
});

// ---- flows never fall silent ------------------------------------------------------------------

test('asking a question: an answer that is not one of the buttons asks again instead of falling silent', function (): void {
    PlatformSetting::put('telegram_questions_group_id', '-1001');
    joinMember(9604, '+971500009604');

    say(9604, '/ask');
    say(9604, 'How long does a visit visa renewal take in Dubai?');
    say(9604, '🛂 Visa and residence');
    say(9604, 'not sure');

    $again = lastTo(9604);
    expect($again['text'])->toBe('Post it with your name, or anonymously?')
        ->and(collect($again['reply_markup']['keyboard'])->flatten(1)->pluck('text')->all())->toContain(enLabel('btn_ask_named'), enLabel('btn_ask_anonymous'));

    say(9604, enLabel('btn_ask_named'));
    expect(\App\Models\Question::query()->count())->toBe(1);
});

test('reporting an employer: anything but yes or no asks again, and the report is kept once answered', function (): void {
    joinMember(9605, '+971500009605');

    tapButton(9605, 's:report');
    say(9605, 'Golden Hands Recruitment');
    say(9605, 'They kept my passport and have not paid me for two months.');
    say(9605, 'maybe later');

    expect(lastTo(9605)['text'])->toBe('May a moderator contact you about it?')
        ->and(EmployerReport::query()->count())->toBe(0);

    say(9605, enLabel('btn_yes'));
    expect(EmployerReport::query()->sole()->only(['employer_name', 'contactable']))->toBe(['employer_name' => 'Golden Hands Recruitment', 'contactable' => true]);
});

test('a sticker in the middle of a step says how to answer or stop; outside a flow it points to /help', function (): void {
    joinMember(9606, '+971500009606');

    tapButton(9606, 'd:search');
    sendSticker(9606);
    expect(lastTo(9606)['text'])->toContain('/cancel');

    say(9606, '/cancel');
    sendSticker(9606);
    expect(lastTo(9606)['text'])->toBe('I did not understand that. Send /help.');
});

test('/safe gives anyone the consulate and MoHRE numbers; only members get the report button', function (): void {
    PlatformSetting::put('safety_consulate_phone', '+971 4 397 1173');
    PlatformSetting::put('safety_mohre_phone', '80060');

    say(9607, '/safe', ['language_code' => 'en']);
    $card = lastTo(9607);
    expect($card['text'])->toContain('+971 4 397 1173')->toContain('80060')
        ->and($card['reply_markup'])->toBeNull();

    joinMember(9608, '+971500009608');
    say(9608, '/safe');
    expect(buttonsOf(lastTo(9608)))->toBe(['s:report']);
});

test('a banned account flooding the bot hears that it is banned once an hour, not once a message', function (): void {
    joinMember(9612, '+971500009612')->forceFill(['banned_at' => now()])->save();

    foreach (range(1, 5) as $i) {
        say(9612, 'hello '.$i);
    }
    expect(collect(textsTo(9612))->filter(fn (string $t) => str_contains($t, 'This account is banned')))->toHaveCount(1);

    $this->travel(61)->minutes();
    say(9612, 'hello again');
    expect(collect(textsTo(9612))->filter(fn (string $t) => str_contains($t, 'This account is banned')))->toHaveCount(2);
});

test('someone who left is told how to join again, not "send /help"', function (): void {
    joinMember(9613, '+971500009613')->forceFill(['left_at' => now()])->save();

    say(9613, 'Where can I renew my Emirates ID?');

    expect(lastTo(9613)['text'])->toBe('You are not a member yet. Send /start to join.');
});

// ---- admin and public endpoints --------------------------------------------------------------

test('the moderation queue counts strikes for every reported member in one query', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    $reporter = joinMember(9610, '+971500009610');
    $report = function (int $n) use ($reporter): void {
        $reported = Member::query()->create([
            'telegram_user_id' => 970000 + $n, 'chat_id' => 970000 + $n, 'phone_hash' => hash('sha256', 'p'.$n), 'phone_last4' => '0000',
            'phone_country' => '971', 'display_name' => 'Reported '.$n, 'language' => 'en', 'verified_at' => now(), 'agreed_rules_at' => now(),
        ]);
        GroupReport::query()->create(['reporter_id' => $reporter->id, 'chat_id' => -1001, 'message_id' => $n, 'reported_member_id' => $reported->id, 'excerpt' => 'x', 'status' => 'open']);
    };
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/admin/moderation')->assertOk();
        // Earlier webhooks' after-response jobs are looked up again under test; not the page's.
        $count = collect(DB::getQueryLog())->reject(fn (array $q) => str_contains($q['query'], 'telegram_updates'))->count();
        DB::disableQueryLog();

        return $count;
    };

    $report(1);
    $one = $queries();
    foreach (range(2, 6) as $n) {
        $report($n);
    }

    expect($queries())->toBe($one);
});

test('an admin filter that is not a number is ignored, not a database error', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    fakeClassifier();
    ingest(['title' => 'Driver one']);

    $this->getJson('/api/admin/jobs?status=all&source_id=abc')->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/admin/businesses?status=all&city_id=abc')->assertOk();
});

test('settings refuse a group id, a support name or a link that cannot be right', function (): void {
    Sanctum::actingAs(admin(), ['admin']);

    $this->putJson('/api/admin/settings', ['telegram_questions_group_id' => 'Habesha Q&A'])->assertStatus(422)->assertJsonValidationErrors('telegram_questions_group_id');
    $this->putJson('/api/admin/settings', ['support_telegram_username' => 'call me on 050'])->assertStatus(422)->assertJsonValidationErrors('support_telegram_username');
    $this->putJson('/api/admin/settings', ['telegram_channel_link' => 'javascript:alert(1)'])->assertStatus(422)->assertJsonValidationErrors('telegram_channel_link');
    $this->putJson('/api/admin/settings', ['allowed_phone_countries' => 'UAE, Ethiopia'])->assertStatus(422)->assertJsonValidationErrors('allowed_phone_countries');

    $this->putJson('/api/admin/settings', [
        'telegram_questions_group_id' => '-1001234567890',
        'support_telegram_username' => '@habesha_help',
        'telegram_channel_link' => 'https://t.me/habesha_jobs',
        'allowed_phone_countries' => '971, 251',
    ])->assertOk()->assertJsonPath('settings.telegram_questions_group_id', '-1001234567890');
});

test('a failed scheduler run does not show its error to the caller', function (): void {
    PlatformSetting::put('tick_token', RuntimeEnv::conceal($token = str_repeat('k', 40)));
    Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('SQLSTATE[08006] could not connect to server at 10.0.4.7, password authentication failed'));

    $response = $this->withHeader('X-Tick-Token', $token)->postJson('/api/system/tick')->assertStatus(500);

    expect($response->getContent())->not->toContain('SQLSTATE')->not->toContain('10.0.4.7');
});

test('the public board counts trips, parcels and free kilos in the database', function (): void {
    $member = joinMember(9611, '+971500009611');
    foreach ([['traveller', 10, 6], ['traveller', 8, null], ['sender', 3, null]] as [$kind, $kilos, $left]) {
        \App\Models\GoodsPost::query()->create([
            'member_id' => $member->id, 'kind' => $kind, 'direction' => 'to_et', 'date' => now('Asia/Dubai')->addDays(5)->toDateString(),
            'city_from' => 'dubai', 'city_to' => 'addis_ababa', 'kilos' => $kilos, 'kilos_left' => $left, 'categories' => ['clothes'], 'status' => 'open',
        ]);
    }

    $this->getJson('/api/public/goods')->assertOk()->assertJsonPath('stats', ['trips' => 2, 'parcels' => 1, 'deals' => 0, 'kilos' => 14]);
});

// ---- the index migration ---------------------------------------------------------------------

test('the launch indexes are in place and the migration is safe to run again', function (): void {
    $migration = require database_path('migrations/2026_11_06_000076_add_launch_indexes.php');

    expect(Schema::hasIndex('goods_posts', 'goods_posts_member_status_idx'))->toBeTrue()
        ->and(Schema::hasIndex('assistant_messages', 'assistant_messages_member_idx'))->toBeTrue()
        ->and(Schema::hasIndex('telegram_updates', 'telegram_updates_received_at_idx'))->toBeTrue();

    $migration->up();
    $migration->down();
    expect(Schema::hasIndex('goods_posts', 'goods_posts_member_status_idx'))->toBeFalse();
    $migration->up();
    $migration->up();
    expect(Schema::hasIndex('goods_posts', 'goods_posts_member_status_idx'))->toBeTrue();
});
