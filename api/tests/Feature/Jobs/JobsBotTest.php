<?php

use App\Models\Conversation;
use App\Models\Job;
use App\Models\JobFilter;
use App\Models\JobReport;
use App\Models\JobSave;
use App\Models\JobSource;
use App\Models\Member;

/** Press an inline button as this Telegram user, in their DM or in a channel. */
function press(int $userId, string $data, int|string $chatId = null, string $chatType = 'private', int $messageId = 55): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'callback_query' => [
            'id' => 'cb'.nextUpdateId(),
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Abebe', 'language_code' => 'en'],
            'message' => ['message_id' => $messageId, 'chat' => ['id' => $chatId ?? $userId, 'type' => $chatType]],
            'data' => $data,
        ],
    ])->assertOk();
}

function lastCallbackAnswer(): array
{
    $answers = telegramCalls('answerCallbackQuery');

    return end($answers) ?: [];
}

beforeEach(function (): void {
    fakeClassifier();
});

test('/jobs opens the filter screen, starting from the trades chosen at sign-up', function (): void {
    joinMember(60);

    say(60, '/jobs');

    $menu = lastMessage();
    $data = collect($menu['reply_markup']['inline_keyboard'])->flatten(1)->pluck('callback_data')->all();

    expect($menu['text'])->toContain('Job alerts')->toContain('Work: Driver')->toContain('Where: All')->toContain('Alerts: On')
        ->and($data)->toContain('jf:cats', 'jf:ems', 'jf:sal', 'jf:kw', 'jf:alerts', 'jf:latest', 'jf:saved')
        ->and(JobFilter::query()->first()->categories)->toBe(['driver']);
});

test('category, emirate and salary buttons edit the filter and the same message', function (): void {
    $member = joinMember(61);

    press(61, 'jf:cat:security');
    press(61, 'jf:cat:driver');
    press(61, 'jf:em:sharjah');
    press(61, 'jf:sal:2500');
    press(61, 'jf:cat:astronaut');

    $filter = JobFilter::query()->where('member_id', $member->id)->first();
    expect($filter->categories)->toBe(['security'])
        ->and($filter->emirates)->toBe(['sharjah'])
        ->and($filter->salary_min)->toBe(2500);

    $edits = telegramCalls('editMessageText');
    expect($edits)->not->toBeEmpty()
        ->and(end($edits)['message_id'])->toBe(55)
        ->and(count(telegramCalls('answerCallbackQuery')))->toBe(5);
});

test('the keyword is typed after pressing Keyword, and can be cleared', function (): void {
    $member = joinMember(62);

    press(62, 'jf:kw');
    expect(Conversation::query()->where('telegram_user_id', 62)->value('state'))->toBe('jobs_keyword');

    say(62, 'x');
    expect(lastText())->toContain('2 to 40 letters');

    say(62, 'Deira');
    expect(JobFilter::query()->where('member_id', $member->id)->value('keyword'))->toBe('Deira')
        ->and(lastText())->toContain('Keyword: Deira')
        ->and(Conversation::query()->where('telegram_user_id', 62)->value('state'))->toBe('idle');

    press(62, 'jf:kwclear');
    expect(JobFilter::query()->where('member_id', $member->id)->value('keyword'))->toBeNull();
});

test('Latest jobs lists open jobs that match the filter, newest first', function (): void {
    joinMember(63);
    ingest(['title' => 'Truck Driver']);
    fakeClassifier(['category' => 'salon', 'title' => 'Hair Stylist']);
    ingest(['title' => 'Hair Stylist']);

    press(63, 'jf:latest');

    expect(lastText())->toContain('Latest matching jobs')->toContain('Truck Driver')->not->toContain('Hair Stylist');
});

test('a member saves a job from the channel card, finds it in /saved, and can unsave it', function (): void {
    $channel = jobsChannel();
    $member = joinMember(64);
    $job = ingest(['title' => 'Delivery Rider']);

    press(64, 'job:save:'.$job->id, $channel, 'channel', 1);
    expect(JobSave::query()->where('member_id', $member->id)->count())->toBe(1)
        ->and(lastCallbackAnswer()['text'])->toContain('Saved');

    say(64, '/saved');
    expect(lastText())->toContain('Your saved jobs')->toContain('Delivery Rider');

    press(64, 'job:save:'.$job->id, $channel, 'channel', 1);
    expect(JobSave::query()->count())->toBe(0);
});

test('a non-member pressing Save is sent to the bot to join', function (): void {
    \App\Models\PlatformSetting::put('telegram_bot_username', 'HabeshaDubaiBot');
    $channel = jobsChannel();
    $job = ingest();

    press(999, 'job:save:'.$job->id, $channel, 'channel', 1);

    expect(JobSave::query()->count())->toBe(0)
        ->and(lastCallbackAnswer()['url'] ?? null)->toBe('https://t.me/HabeshaDubaiBot?start=jobs');
});

test('three distinct reports take a card off the channel and into review; a repeat report counts once', function (): void {
    $channel = jobsChannel();
    $job = ingest();
    foreach ([70, 71, 72] as $i => $id) {
        joinMember($id, '+97150123400'.$i);
    }

    press(70, 'job:report:'.$job->id, $channel, 'channel', 1);
    press(70, 'job:report:'.$job->id, $channel, 'channel', 1);
    expect(lastCallbackAnswer()['text'])->toContain('already reported')
        ->and(JobReport::query()->count())->toBe(1);

    press(71, 'job:report:'.$job->id, $channel, 'channel', 1);
    expect($job->fresh()->status)->toBe('published');

    press(72, 'job:report:'.$job->id, $channel, 'channel', 1);
    $job->refresh();
    expect($job->status)->toBe('review')
        ->and($job->reports_count)->toBe(3)
        ->and($job->channel_message_id)->toBeNull()
        ->and(collect(telegramCalls('deleteMessage'))->firstWhere('chat_id', $channel))->not->toBeNull();
});

test('a moderator forwarding a channel post adds it to the pipeline; a member forwarding does not', function (): void {
    $mod = joinMember(80, '+971501110001');
    $mod->forceFill(['role' => 'moderator'])->save();
    joinMember(81, '+971501110002');

    $forward = fn (int $userId) => test()->postJson('/api/telegram/webhook/'.webhookSecret(), telegramMessage($userId, [
        'text' => "Cleaners needed in Al Quoz\nWarehouse cleaning, AED 1600, transport provided, start Monday.",
        'forward_origin' => ['type' => 'channel', 'chat' => ['id' => -100555, 'type' => 'channel', 'username' => 'uae_jobs_daily'], 'message_id' => 4321, 'date' => time()],
    ]));

    $forward(81);
    expect(Job::query()->count())->toBe(0);

    $forward(80);
    $job = Job::query()->first();
    expect($job)->not->toBeNull()
        ->and($job->url)->toBe('https://t.me/uae_jobs_daily/4321')
        ->and($job->source->key)->toBe('forwarded')
        ->and(lastText())->toContain('Added to the job queue');

    $forward(80);
    expect(lastText())->toContain('already have this listing')->and(Job::query()->count())->toBe(1);
});

test('posts in a channel registered as a telegram source are ingested; other channels are ignored', function (): void {
    JobSource::query()->create(['key' => 'tg-partner', 'name' => 'Partner channel', 'kind' => 'telegram', 'config' => ['chat_id' => '-100777'], 'interval_minutes' => 0, 'active' => true]);

    $post = fn (int $chatId) => test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'channel_post' => ['message_id' => nextUpdateId(), 'date' => time(), 'chat' => ['id' => $chatId, 'type' => 'channel', 'username' => 'partner_jobs'],
            'text' => "Waitress wanted\nCafe in Business Bay, AED 2200 plus tips, Arabic or English."],
    ])->assertOk();

    $post(-100888);
    expect(Job::query()->count())->toBe(0);

    $post(-100777);
    expect(Job::query()->count())->toBe(1)->and(Job::query()->first()->source->key)->toBe('tg-partner');
});

test('/jobs and /saved need a finished sign-up', function (): void {
    say(90, '/jobs', ['language_code' => 'en']);
    expect(lastText())->toContain('Send /start');

    say(90, '/saved', ['language_code' => 'en']);
    expect(lastText())->toContain('Send /start');
});
