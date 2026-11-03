<?php

use App\Models\GroupReport;
use App\Models\Member;
use App\Models\MemberStrike;
use App\Models\PlatformSetting;
use Laravel\Sanctum\Sanctum;

const MOD_QGROUP = '-1009000';
const MOD_GGROUP = '-1009100';

function inGroup(string $group, int $userId, string $text, array $extra = []): int
{
    $id = nextUpdateId();
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), ['update_id' => nextUpdateId(), 'message' => $extra + [
        'message_id' => $id,
        'date' => time(),
        'chat' => ['id' => (int) $group, 'type' => 'supergroup', 'title' => 'G'],
        'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'X'],
        'text' => $text,
    ]])->assertOk();

    return $id;
}

function deleted(): array
{
    return collect(telegramCalls('deleteMessage'))->pluck('message_id')->all();
}

beforeEach(function (): void {
    PlatformSetting::put('telegram_questions_group_id', MOD_QGROUP);
    PlatformSetting::put('telegram_goods_group_id', MOD_GGROUP);
});

test('a non-member posting in a community group is removed and kicked', function (): void {
    $id = inGroup(MOD_QGROUP, 800, 'hello all');

    expect(deleted())->toContain($id)
        ->and(collect(telegramCalls('banChatMember'))->firstWhere('user_id', 800))->not->toBeNull()
        ->and(collect(telegramCalls('unbanChatMember'))->firstWhere('user_id', 800))->not->toBeNull();
});

test('a new member cannot post links or phone numbers in the first week; an older member can', function (): void {
    $new = joinMember(801, '+971500000801');
    $old = joinMember(802, '+971500000802');
    $old->forceFill(['verified_at' => now()->subDays(30)])->save();

    $link = inGroup(MOD_QGROUP, 801, 'Check this offer https://cheap-visas.example.com');
    $phone = inGroup(MOD_QGROUP, 801, 'Call me on 050 123 4567 for the room');
    $fine = inGroup(MOD_QGROUP, 801, 'Thank you for the answer, it helped a lot.');
    $oldLink = inGroup(MOD_QGROUP, 802, 'The official page is https://www.mohre.gov.ae');

    expect(deleted())->toContain($link, $phone)->not->toContain($fine, $oldLink)
        ->and(MemberStrike::query()->where('member_id', $new->id)->count())->toBe(2)
        ->and(collect(sentMessages())->where('chat_id', 801)->last()['text'])->toContain('first 7 days');
});

test('three removals in a day mute the member for 24 hours in both groups', function (): void {
    $m = joinMember(803);

    foreach (range(1, 3) as $i) {
        inGroup(MOD_QGROUP, 803, 'Visit www.spam'.$i.'.com now');
    }

    $m->refresh();
    expect($m->isMuted())->toBeTrue()
        ->and(collect(telegramCalls('restrictChatMember'))->pluck('chat_id')->all())->toBe([MOD_QGROUP, MOD_GGROUP])
        ->and(collect(sentMessages())->where('chat_id', 803)->last()['text'])->toContain('24 hours');

    $later = inGroup(MOD_QGROUP, 803, 'just saying hi');
    expect(deleted())->toContain($later);
});

test('scam phrases are removed everywhere; banned goods items only on the goods board', function (): void {
    PlatformSetting::put('job_scam_phrases', "western union only\n");
    $m = joinMember(804);
    $m->forceFill(['verified_at' => now()->subDays(30)])->save();

    $scam = inGroup(MOD_QGROUP, 804, 'Pay by Western Union only and get your visa');
    $goldGoods = inGroup(MOD_GGROUP, 804, 'Need someone to carry gold to Addis on Friday');
    $goldQuestion = inGroup(MOD_QGROUP, 804, 'What is the price of gold in Dubai today?');

    expect(deleted())->toContain($scam, $goldGoods)->not->toContain($goldQuestion);
});

test('leaders and moderators are never touched by the automatic checks', function (): void {
    $leader = joinMember(805);
    $leader->forceFill(['role' => 'leader'])->save();

    $id = inGroup(MOD_QGROUP, 805, 'Church program: https://church.example.com, call 050 123 4567');

    expect(deleted())->not->toContain($id);
});

test('/report must be a reply; three distinct reports take the message down; the queue resolves it', function (): void {
    $bad = joinMember(810, '+971500000810');
    $bad->forceFill(['verified_at' => now()->subDays(30)])->save();
    foreach ([811, 812, 813] as $i => $id) {
        joinMember($id, '+97150000081'.($i + 1));
    }

    $target = inGroup(MOD_QGROUP, 810, 'You people are all useless, go back home.');

    $noReply = inGroup(MOD_QGROUP, 811, '/report');
    expect(deleted())->toContain($noReply)
        ->and(collect(sentMessages())->where('chat_id', 811)->last()['text'])->toContain('reply to it with /report');

    $reply = ['reply_to_message' => ['message_id' => $target, 'from' => ['id' => 810, 'is_bot' => false, 'first_name' => 'X'], 'chat' => ['id' => (int) MOD_QGROUP, 'type' => 'supergroup'], 'date' => time(), 'text' => 'You people are all useless, go back home.']];
    inGroup(MOD_QGROUP, 811, '/report', $reply);
    inGroup(MOD_QGROUP, 811, '/report', $reply);
    inGroup(MOD_QGROUP, 812, '/report', $reply);
    expect(GroupReport::query()->count())->toBe(2)->and(deleted())->not->toContain($target);

    inGroup(MOD_QGROUP, 813, '/report@HabeshaDubaiBot', $reply);
    expect(deleted())->toContain($target)
        ->and(GroupReport::query()->where('action', 'hidden')->count())->toBe(3);

    Sanctum::actingAs(admin(), ['admin']);
    $this->getJson('/api/admin/moderation')->assertOk()
        ->assertJsonPath('queue.0.reports', 3)->assertJsonPath('queue.0.hidden', true)
        ->assertJsonPath('queue.0.reported.name', 'Abebe K')->assertJsonPath('queue.0.group', 'Questions group');

    $this->postJson('/api/admin/moderation/resolve', ['chat_id' => (int) MOD_QGROUP, 'message_id' => $target, 'action' => 'warn', 'reason' => 'Insulting other members.'])->assertOk();

    expect(GroupReport::query()->where('status', 'open')->count())->toBe(0)
        ->and(collect(sentMessages())->where('chat_id', 810)->last()['text'])->toContain('A moderator warned you: Insulting other members.');

    $this->getJson('/api/admin/moderation')->assertOk()->assertJsonCount(0, 'queue')->assertJsonPath('strikes.0.kind', 'warning');
});

test('three warnings in 90 days mean a 7-day mute', function (): void {
    $m = joinMember(820);
    $moderator = app(\App\Bot\GroupModerator::class);

    $moderator->warn($m, 'one', null);
    $moderator->warn($m, 'two', null);
    expect($m->fresh()->isMuted())->toBeFalse();

    $moderator->warn($m, 'three', null);
    expect($m->fresh()->muted_until->greaterThan(now()->addDays(6)))->toBeTrue();
});

test('a ban from the queue bans in both groups and stops the bot', function (): void {
    $bad = joinMember(830, '+971500000830');
    $bad->forceFill(['verified_at' => now()->subDays(30)])->save();
    joinMember(831, '+971500000831');
    $target = inGroup(MOD_GGROUP, 830, 'Selling visas, message me privately.');
    inGroup(MOD_GGROUP, 831, '/report', ['reply_to_message' => ['message_id' => $target, 'from' => ['id' => 830, 'is_bot' => false, 'first_name' => 'X'], 'chat' => ['id' => (int) MOD_GGROUP, 'type' => 'supergroup'], 'date' => time(), 'text' => 'Selling visas']]);

    Sanctum::actingAs(admin(), ['admin']);
    $this->postJson('/api/admin/moderation/resolve', ['chat_id' => (int) MOD_GGROUP, 'message_id' => $target, 'action' => 'ban', 'reason' => 'Selling visas'])->assertOk();

    expect(Member::findByTelegramUser(830)->isBanned())->toBeTrue()
        ->and(collect(telegramCalls('banChatMember'))->where('user_id', 830)->pluck('chat_id')->all())->toContain(MOD_QGROUP, MOD_GGROUP)
        ->and(deleted())->toContain($target);

    say(830, '/start');
    expect(lastText())->toContain('banned');
});

test('the moderation queue denies a guest', function (): void {
    $this->getJson('/api/admin/moderation')->assertStatus(401);
    $this->postJson('/api/admin/moderation/resolve')->assertStatus(401);
});
