<?php

use App\Models\Member;
use App\Models\PlatformSetting;
use App\Models\Question;
use App\Models\QuestionReply;

const QGROUP = '-1009000';

/** A member's message in the questions group, inside a forum topic or as a reply. */
function groupSay(int $userId, string $text, ?int $threadId = null, ?int $replyTo = null): void
{
    $message = [
        'message_id' => nextUpdateId(),
        'date' => time(),
        'chat' => ['id' => (int) QGROUP, 'type' => 'supergroup', 'title' => 'Questions', 'is_forum' => $threadId !== null],
        'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'X'],
        'text' => $text,
    ];
    if ($threadId !== null) {
        $message['message_thread_id'] = $threadId;
        $message['is_topic_message'] = true;
    }
    if ($replyTo !== null) {
        $message['reply_to_message'] = ['message_id' => $replyTo, 'chat' => $message['chat'], 'date' => time()];
    }

    test()->postJson('/api/telegram/webhook/'.webhookSecret(), ['update_id' => nextUpdateId(), 'message' => $message])->assertOk();
}

function pressQ(int $userId, string $data, int|string|null $chatId = null, string $type = 'private'): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'callback_query' => [
            'id' => 'cb'.nextUpdateId(),
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'X', 'language_code' => 'en'],
            'message' => ['message_id' => 90, 'chat' => ['id' => $chatId ?? $userId, 'type' => $type]],
            'data' => $data,
        ],
    ])->assertOk();
}

function askQuestion(int $userId, string $text = 'How do I renew my residence visa when my employer has not paid the fees yet?', bool $anonymous = false): Question
{
    say($userId, '/ask');
    say($userId, $text);
    say($userId, '🛂 Visa and residence');
    say($userId, $anonymous ? '🙈 Anonymously' : '👤 With my name');

    return Question::query()->latest('id')->firstOrFail();
}

beforeEach(function (): void {
    PlatformSetting::put('telegram_questions_group_id', QGROUP);
    PlatformSetting::put('community_language', 'en');
});

test('/ask posts the question in its own forum topic with a Mark solved button', function (): void {
    TelegramFake::$results['createForumTopic'] = ['message_thread_id' => 501, 'name' => 'x'];
    $asker = joinMember(400);

    $q = askQuestion(400);

    expect($q->member_id)->toBe($asker->id)->and($q->category)->toBe('visa')->and($q->forum_topic_id)->toBe(501)->and($q->group_message_id)->toBe(1);

    $topic = telegramCalls('createForumTopic')[0];
    $post = collect(telegramCalls('sendMessage'))->firstWhere('chat_id', QGROUP);

    expect($topic['name'])->toStartWith('❓ How do I renew')
        ->and($post['message_thread_id'])->toBe(501)
        ->and($post['text'])->toContain('How do I renew my residence visa')->toContain('Abebe K')->toContain('#visa')
        ->and($post['reply_markup']['inline_keyboard'][0][0]['callback_data'])->toBe('q:solve:'.$q->id)
        ->and(lastText())->toContain('Your question is posted');
});

test('an anonymous question hides the asker in the group', function (): void {
    joinMember(401);

    askQuestion(401, 'Is it allowed for my sponsor to keep my passport at home all the time?', anonymous: true);

    $post = collect(telegramCalls('sendMessage'))->firstWhere('chat_id', QGROUP);
    expect($post['text'])->toContain('A member')->not->toContain('Abebe');
});

test('a group without topics still works: answers are replies to the question message', function (): void {
    TelegramFake::$results['sendMessage'] = ['message_id' => 333];
    joinMember(402, '+971500000402');
    joinMember(403, '+971500000403');
    $q = askQuestion(402);

    expect($q->forum_topic_id)->toBeNull()->and($q->group_message_id)->toBe(333);

    groupSay(403, 'You can renew through the employer only; ask MoHRE if they refuse.', replyTo: 333);

    expect(QuestionReply::query()->where('question_id', $q->id)->count())->toBe(1);
});

test('answers in the topic are recorded, the first per member earns a point, and the asker hears about it', function (): void {
    TelegramFake::$results['createForumTopic'] = ['message_thread_id' => 600];
    joinMember(410, '+971500000410');
    $helper = joinMember(411, '+971500000411');
    $q = askQuestion(410);

    groupSay(411, 'Your employer must pay the renewal. File a complaint with MoHRE.', threadId: 600);
    groupSay(411, 'Also keep copies of your salary slips.', threadId: 600);
    groupSay(410, 'Thank you!', threadId: 600);
    groupSay(999, 'I am not a member', threadId: 600);
    groupSay(411, 'Unrelated chat in another topic', threadId: 601);

    expect(QuestionReply::query()->where('question_id', $q->id)->count())->toBe(3)
        ->and($helper->fresh()->helper_points)->toBe(1);

    $toAsker = collect(sentMessages())->where('chat_id', 410)->last();
    expect($toAsker['text'])->toContain('answered your question')->toContain('Your employer must pay')
        ->and($toAsker['reply_markup']['inline_keyboard'][0][0]['callback_data'])->toBe('q:solve:'.$q->id);
});

test('the asker marks the solving answer: +5 points, topic renamed, group told, answerer thanked', function (): void {
    TelegramFake::$results['createForumTopic'] = ['message_thread_id' => 700];
    joinMember(420, '+971500000420');
    $helper = joinMember(421, '+971500000421');
    $q = askQuestion(420);
    groupSay(421, 'Your employer must pay the renewal. File a complaint with MoHRE.', threadId: 700);
    $reply = QuestionReply::query()->firstOrFail();

    pressQ(420, 'q:solve:'.$q->id, QGROUP, 'supergroup');
    $picker = collect(sentMessages())->where('chat_id', 420)->last();
    expect($picker['text'])->toContain('Which answer solved')
        ->and($picker['reply_markup']['inline_keyboard'][0][0]['callback_data'])->toBe('q:pick:'.$reply->id)
        ->and(collect(telegramCalls('answerCallbackQuery'))->last()['text'])->toContain('private message');

    pressQ(420, 'q:pick:'.$reply->id);

    $q->refresh();
    expect($q->status)->toBe('solved')->and($q->solved_reply_id)->toBe($reply->id)
        ->and($helper->fresh()->helper_points)->toBe(6)
        ->and(telegramCalls('editForumTopic')[0]['name'])->toStartWith('✅')
        ->and(collect(sentMessages())->firstWhere(fn ($m) => str_contains($m['text'], 'Solved: the answer by')))->not->toBeNull()
        ->and(collect(sentMessages())->where('chat_id', 421)->last()['text'])->toContain('+5 helper points (now 6)');
});

test('only the asker or a moderator may mark solved', function (): void {
    TelegramFake::$results['createForumTopic'] = ['message_thread_id' => 800];
    joinMember(430, '+971500000430');
    joinMember(431, '+971500000431');
    $mod = joinMember(432, '+971500000432');
    $mod->forceFill(['role' => 'moderator'])->save();
    $q = askQuestion(430);
    groupSay(431, 'Here is the answer to your question about renewal.', threadId: 800);

    pressQ(431, 'q:solve:'.$q->id, QGROUP, 'supergroup');
    expect(collect(telegramCalls('answerCallbackQuery'))->last()['text'])->toContain('Only the person who asked');

    pressQ(432, 'q:pick:'.QuestionReply::query()->value('id'));
    expect($q->fresh()->status)->toBe('solved');
});

test('the profile shows the helper rank', function (): void {
    $m = joinMember(440);
    $m->forceFill(['helper_points' => 120])->save();

    say(440, '/profile');

    expect(lastText())->toContain('Helper points: 120 · Trusted');
});

test('asking is limited per day, and needs the group to be set', function (): void {
    joinMember(450);
    PlatformSetting::put('questions_per_day', '1');
    askQuestion(450);

    say(450, '/ask');
    expect(lastText())->toContain('asked 1 questions today');

    PlatformSetting::put('telegram_questions_group_id', '');
    PlatformSetting::put('questions_per_day', '5');
    say(450, '/ask');
    expect(lastText())->toContain('not set up yet');
});

test('/myquestions lists questions with a solve button when there are answers', function (): void {
    TelegramFake::$results['createForumTopic'] = ['message_thread_id' => 900];
    joinMember(460, '+971500000460');
    joinMember(461, '+971500000461');
    $q = askQuestion(460);
    groupSay(461, 'An answer with enough words to count as one.', threadId: 900);

    say(460, '/myquestions');

    expect(lastText())->toContain('Your questions')->toContain('1 answers')
        ->and(lastMessage()['reply_markup']['inline_keyboard'][0][0]['callback_data'])->toBe('q:solve:'.$q->id);
});
