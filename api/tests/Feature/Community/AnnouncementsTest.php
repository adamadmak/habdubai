<?php

use Anthropic\Client;
use App\Ai\ClaudeJson;
use App\Bot\AnnouncementsFlow;
use App\Models\Announcement;
use App\Models\AnnouncementTopic;
use App\Models\Member;
use App\Support\JobSettings;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function topic(string $key): AnnouncementTopic
{
    return AnnouncementTopic::query()->where('key', $key)->firstOrFail();
}

function leader(int $userId, string $phone, array $topicKeys): Member
{
    $m = joinMember($userId, $phone);
    $m->forceFill(['role' => 'leader'])->save();
    foreach ($topicKeys as $key) {
        DB::table('leader_topics')->insert(['member_id' => $m->id, 'topic_id' => topic($key)->id]);
    }

    return $m;
}

function sendPhoto(int $userId): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), telegramMessage($userId, [
        'photo' => [['file_id' => 'small-id', 'width' => 90, 'height' => 90], ['file_id' => 'big-photo-id', 'width' => 1280, 'height' => 960]],
    ]))->assertOk();
}

beforeEach(function (): void {
    jobsChannel('@habesha_channel');
});

test('the nine topics are seeded, with approval on deaths and fundraisers', function (): void {
    expect(AnnouncementTopic::query()->count())->toBe(9)
        ->and(AnnouncementTopic::query()->where('needs_approval', true)->pluck('key')->all())->toBe(['condolences', 'fundraisers'])
        ->and(AnnouncementTopic::query()->where('default_on', true)->pluck('key')->all())->toBe(['consulate', 'safety', 'platform']);
});

test('a plain member cannot announce', function (): void {
    joinMember(700);

    say(700, '/announce');

    expect(lastText())->toContain('Only community leaders');
});

test('a leader posts an event in a granted topic: channel card, DMs to subscribers, delete button', function (): void {
    leader(701, '+971500000701', ['church']);
    $subscriber = joinMember(702, '+971500000702');
    DB::table('member_topic_prefs')->insert(['member_id' => $subscriber->id, 'topic_id' => topic('church')->id, 'subscribed' => true]);
    joinMember(703, '+971500000703');

    say(701, '/announce');
    expect(collect(lastMessage()['reply_markup']['keyboard'])->flatten(1)->pluck('text')->all())->toBe(['⛪ Church', '❌ Cancel']);

    say(701, '⛪ Church');
    say(701, 'short');
    expect(lastText())->toContain('10 to 2000');
    say(701, 'Timket celebration at St. Mary church. Everyone welcome, bring white clothes.');
    sendPhoto(701);
    say(701, '2020-01-01');
    expect(lastText())->toContain('today or later');
    $date = now()->addDays(10)->toDateString();
    say(701, $date);
    say(701, 'St. Mary Church, Oud Metha');

    expect(lastText())->toContain('Preview')->toContain('Timket celebration')->toContain('St. Mary Church, Oud Metha');

    say(701, '📣 Publish');

    $a = Announcement::query()->firstOrFail();
    expect($a->status)->toBe('published')->and($a->photo_file_id)->toBe('big-photo-id')->and($a->event_date->toDateString())->toBe($date)
        ->and($a->dm_count)->toBe(1);

    $post = collect(telegramCalls('sendPhoto'))->firstWhere('chat_id', '@habesha_channel');
    expect($post['photo'])->toBe('big-photo-id')->and($post['caption'])->toContain('Timket celebration')->toContain('📅');

    expect(collect(telegramCalls('sendPhoto'))->pluck('chat_id')->all())->toContain(702)->not->toContain(703)
        ->and(lastText())->toContain('Posted. 1 members')
        ->and(lastMessage()['reply_markup']['inline_keyboard'][0][0]['callback_data'])->toBe('an:del:'.$a->id);

    pressQ(701, 'an:del:'.$a->id);
    expect($a->fresh()->status)->toBe('deleted')
        ->and(collect(telegramCalls('deleteMessage'))->firstWhere('chat_id', '@habesha_channel'))->not->toBeNull();
});

test('a leader cannot use a topic they were not given', function (): void {
    leader(704, '+971500000704', ['church']);

    say(704, '/announce');
    say(704, '🕌 Mosque');

    expect(lastText())->toContain('Which topic');
});

test('condolences from a leader wait for a moderator; approve posts and tells the poster, with the fundraiser warning on fundraisers', function (): void {
    leader(710, '+971500000710', ['condolences', 'fundraisers']);
    Sanctum::actingAs(admin(), ['admin']);

    say(710, '/announce');
    say(710, '🕯 Deaths and condolences');
    say(710, 'Our brother Dawit passed away in Sharjah. Prayers on Friday.');
    say(710, '⏭ Skip');
    say(710, '⏭ Skip');
    expect(lastText())->toContain('checked by a moderator');
    say(710, '📣 Publish');

    $a = Announcement::query()->firstOrFail();
    expect($a->status)->toBe('pending')->and(lastText())->toContain('A moderator will check it')
        ->and(collect(sentMessages())->firstWhere('chat_id', '@habesha_channel'))->toBeNull();

    $this->getJson('/api/admin/announcements')->assertOk()->assertJsonPath('data.0.status', 'pending')->assertJsonPath('counts.pending', 1);
    $this->postJson('/api/admin/announcements/'.$a->id.'/approve')->assertOk()->assertJsonPath('announcement.status', 'published');

    expect(collect(sentMessages())->firstWhere('chat_id', '@habesha_channel')['text'])->toContain('Dawit')
        ->and(collect(sentMessages())->where('chat_id', 710)->last()['text'])->toContain('approved and posted');

    say(710, '/announce');
    say(710, '🤝 Fundraisers');
    say(710, 'Help for the family of our brother Dawit to bring him home.');
    say(710, '⏭ Skip');
    say(710, '⏭ Skip');
    say(710, '📣 Publish');
    $f = Announcement::query()->latest('id')->first();
    $this->postJson('/api/admin/announcements/'.$f->id.'/reject', ['note' => 'Please add the organiser\'s phone number.'])->assertOk();
    expect(collect(sentMessages())->where('chat_id', 710)->last()['text'])->toContain('not posted')->toContain('organiser');
});

test('a moderator posts in an approval topic straight away', function (): void {
    $mod = joinMember(720, '+971500000720');
    $mod->forceFill(['role' => 'moderator'])->save();

    say(720, '/announce');
    say(720, '🤝 Fundraisers');
    say(720, 'Collection for the Abebe family at the community hall on Friday.');
    say(720, '⏭ Skip');
    say(720, '⏭ Skip');
    say(720, '📣 Publish');

    $a = Announcement::query()->firstOrFail();
    expect($a->status)->toBe('published')
        // The channel card's labels are in Amharic by default.
        ->and(collect(sentMessages())->firstWhere('chat_id', '@habesha_channel')['text'])->toContain('መድረኩ ገንዘብ አይሰበስብም');
});

test('/topics shows defaults and toggles a subscription', function (): void {
    $m = joinMember(730);

    say(730, '/topics');
    $buttons = collect(lastMessage()['reply_markup']['inline_keyboard'])->flatten(1);
    expect($buttons->firstWhere('callback_data', 'tp:'.topic('safety')->id)['text'])->toStartWith('✅')
        ->and($buttons->firstWhere('callback_data', 'tp:'.topic('church')->id)['text'])->toStartWith('⬜');

    pressQ(730, 'tp:'.topic('safety')->id);
    pressQ(730, 'tp:'.topic('church')->id);

    expect(topic('safety')->isSubscribed($m))->toBeFalse()->and(topic('church')->isSubscribed($m))->toBeTrue()
        ->and(topic('safety')->subscribers()->pluck('id')->all())->not->toContain($m->id)
        ->and(topic('church')->subscribers()->pluck('id')->all())->toContain($m->id);
});

test('safety alerts are pinned for 48 hours, then unpinned; events get a reminder the day before', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    joinMember(740);

    $this->postJson('/api/admin/announcements', ['topic_id' => topic('safety')->id, 'text' => 'Heavy rain warning: avoid Al Ittihad road tonight.'])
        ->assertCreated()->assertJsonPath('translated_by', 'copy');
    $alert = Announcement::query()->firstOrFail();
    expect(telegramCalls('pinChatMessage'))->toHaveCount(1)->and($alert->pinned_until)->not->toBeNull();

    $this->travel(49)->hours();
    $this->artisan('announcements:chores')->assertSuccessful();
    expect(telegramCalls('unpinChatMessage'))->toHaveCount(1)->and($alert->fresh()->pinned_until)->toBeNull();

    $this->postJson('/api/admin/announcements', ['topic_id' => topic('platform')->id, 'text' => 'Community meeting about the new bot features.', 'event_date' => now('Asia/Dubai')->addDay()->toDateString()])->assertCreated();
    $before = count(sentMessages());
    $this->artisan('announcements:chores --reminders --any-hour')->assertSuccessful();
    $reminders = collect(sentMessages())->slice($before)->where('chat_id', 740);
    expect($reminders->last()['text'])->toContain('Reminder: tomorrow')->toContain('Community meeting');

    $this->artisan('announcements:chores --reminders --any-hour')->assertSuccessful();
    expect(collect(sentMessages())->slice($before)->where('chat_id', 740))->toHaveCount(1);
});

test('an admin announcement is translated by Claude, and an edit updates the channel', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    JobSettings::setAnthropicKey('sk-ant-api03-test-key-not-real-0000');
    $transport = fakeTransport([[200, claudeResponse(['am' => 'ቆንስላው ሰኞ ዝግ ነው።', 'ti' => 'ቆንስላ ሰኑይ ዕጹው እዩ።', 'en' => 'The consulate is closed on Monday.'])]]);
    app()->instance(ClaudeJson::class, new ClaudeJson(new Client(apiKey: 'sk-ant-test', requestOptions: ['transporter' => $transport, 'maxRetries' => 0])));

    $id = $this->postJson('/api/admin/announcements', ['topic_id' => topic('consulate')->id, 'text' => 'The consulate is closed on Monday.'])
        ->assertCreated()->assertJsonPath('translated_by', 'claude')->assertJsonPath('announcement.text_am', 'ቆንስላው ሰኞ ዝግ ነው።')->json('announcement.id');

    $this->putJson('/api/admin/announcements/'.$id, ['text_am' => 'ቆንስላው ሰኞ እና ማክሰኞ ዝግ ነው።', 'text_ti' => 'x ti', 'text_en' => 'Closed Monday and Tuesday.'])->assertOk();
    expect(collect(telegramCalls('editMessageText'))->last()['text'])->toContain('ቆንስላው ሰኞ እና ማክሰኞ');
});

test('leader topics are granted from the dashboard', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    $m = joinMember(750);

    $this->putJson('/api/admin/members/'.$m->id.'/leader-topics', ['topic_ids' => [topic('church')->id, topic('events')->id]])->assertOk()->assertJsonCount(2, 'topic_ids');
    $this->getJson('/api/admin/members/'.$m->id.'/leader-topics')->assertOk()->assertJsonCount(2, 'topic_ids');

    $m->forceFill(['role' => 'leader'])->save();
    expect(AnnouncementTopic::postableBy($m)->pluck('key')->all())->toBe(['church', 'events']);
});

test('dates are read in the common formats and must not be in the past', function (): void {
    $future = now()->addDays(5);

    expect(AnnouncementsFlow::parseDate($future->format('Y-m-d'))?->toDateString())->toBe($future->toDateString())
        ->and(AnnouncementsFlow::parseDate($future->format('d/m/Y'))?->toDateString())->toBe($future->toDateString())
        ->and(AnnouncementsFlow::parseDate('2020-01-01'))->toBeNull()
        ->and(AnnouncementsFlow::parseDate('next friday'))->toBeNull();
});

test('a leader can send a few announcements a day, not a flood; moderators are not counted', function (): void {
    // 23:30 in Dubai: a day in Dubai, not in UTC, is what counts.
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-10 19:30:00', 'UTC'));
    $leader = leader(709, '+971500000709', ['church']);
    $post = fn (string $text, string $status) => Announcement::query()->create(['topic_id' => topic('church')->id, 'member_id' => $leader->id, 'original_language' => 'en',
        'text_en' => $text, 'text_am' => 'x', 'text_ti' => 'x', 'status' => $status, 'published_at' => $status === 'published' ? now() : null]);

    // A rejected draft does not use up a turn.
    foreach (range(1, AnnouncementsFlow::LEADER_PER_DAY - 1) as $i) {
        $post('Sunday service '.$i, 'published');
    }
    $post('Not this one', 'rejected');
    say(709, '/announce');
    expect(lastTo(709)['text'])->not->toContain('announcements today');
    say(709, '/cancel');

    $post('Sunday service '.AnnouncementsFlow::LEADER_PER_DAY, 'pending');
    say(709, '/announce');
    expect(lastTo(709)['text'])->toContain('posted 5 announcements today');

    // Half an hour later it is a new day in Dubai (still the 10th in UTC).
    $this->travel(31)->minutes();
    say(709, '/announce');
    expect(lastTo(709)['text'])->not->toContain('announcements today');
    say(709, '/cancel');
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-10 19:30:00', 'UTC'));

    $this->travel(1)->day();
    say(709, '/announce');
    expect(lastTo(709)['text'])->not->toContain('announcements today');

    $leader->forceFill(['role' => 'moderator'])->save();
    foreach (range(1, AnnouncementsFlow::LEADER_PER_DAY) as $i) {
        Announcement::query()->create(['topic_id' => topic('church')->id, 'member_id' => $leader->id, 'original_language' => 'en',
            'text_en' => 'Notice '.$i, 'text_am' => 'x', 'text_ti' => 'x', 'status' => 'published', 'published_at' => now()]);
    }
    say(709, '/cancel');
    say(709, '/announce');
    expect(lastTo(709)['text'])->not->toContain('announcements today');
});

test('a topic taken away while a leader is writing is checked again when they press Publish', function (): void {
    $leader = leader(710, '+971500000710', ['church']);

    say(710, '/announce');
    say(710, '⛪ Church');
    say(710, 'Choir practice moves to Saturday evening at the hall.');
    say(710, '⏭ Skip');
    say(710, '⏭ Skip');
    expect(lastTo(710)['text'])->toContain('Preview');

    DB::table('leader_topics')->where('member_id', $leader->id)->delete();
    say(710, '📣 Publish');

    expect(Announcement::query()->count())->toBe(0)
        ->and(lastTo(710)['text'])->toContain('Only community leaders');
});
