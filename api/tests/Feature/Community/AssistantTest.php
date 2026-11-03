<?php

use Anthropic\Client;
use App\Ai\ClaudeJson;
use App\Models\AssistantMessage;
use App\Models\FaqEntry;
use App\Models\KnowledgeArticle;
use App\Models\PlatformSetting;
use App\Support\JobSettings;

function claudeWith(object $transport): void
{
    JobSettings::setAnthropicKey('sk-ant-api03-test-key-not-real-0000');
    app()->instance(ClaudeJson::class, new ClaudeJson(new Client(apiKey: 'sk-ant-test', requestOptions: ['transporter' => $transport, 'maxRetries' => 0])));
}

beforeEach(function (): void {
    KnowledgeArticle::query()->create([
        'title' => 'Who pays for a work visa renewal',
        'body' => 'Under UAE labour law the employer pays all costs of issuing and renewing a work permit and residence visa.',
        'source_name' => 'MoHRE', 'source_url' => 'https://www.mohre.gov.ae/', 'active' => true,
    ]);
    FaqEntry::query()->create([
        'category' => 'housing', 'q_en' => 'Where can I find a bedspace in Deira?', 'a_en' => 'Ask in the housing topic of the questions group.',
        'q_am' => 'ዴይራ ውስጥ አልጋ የት አገኛለሁ?', 'a_am' => 'በጥያቄ ቡድኑ የመኖሪያ ርዕስ ይጠይቁ።', 'active' => true,
    ]);
});

test('free text goes to Claude with the checked knowledge cached, and the answer cites its source', function (): void {
    $transport = fakeTransport([[200, claudeResponse(['answer' => 'Your employer pays for the renewal. You should not pay anything.', 'source_ids' => ['K1'], 'ask_community' => true])]]);
    claudeWith($transport);
    joinMember(500);

    say(500, 'Who pays when my visa is renewed?');

    $body = json_decode((string) $transport->requests[0]->getBody(), true);
    expect($body['system'][1]['text'])->toContain('<entry id="K1"')->toContain('<entry id="F1"')
        ->and($body['system'][1]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($body['output_config']['effort'])->toBe('medium')
        ->and($body['fallbacks'])->toBe('default')
        ->and(end($body['messages'])['content'])->toContain('language="English"')->toContain('Who pays when my visa is renewed?');

    expect(lastText())->toContain('Your employer pays for the renewal')
        ->toContain('<a href="https://www.mohre.gov.ae/">MoHRE</a>')
        ->toContain('Ask the group if your case is different')
        ->and(lastMessage()['reply_markup']['inline_keyboard'][0][0]['callback_data'])->toBe('q:fromai')
        ->and(telegramCalls('sendChatAction'))->not->toBeEmpty();
});

test('the conversation is remembered for follow-ups and trimmed to ten turns', function (): void {
    $transport = fakeTransport(array_fill(0, 7, [200, claudeResponse(['answer' => 'ok', 'source_ids' => [], 'ask_community' => false])]));
    claudeWith($transport);
    $member = joinMember(501);

    foreach (range(1, 7) as $i) {
        say(501, 'Question number '.$i.' about my visa');
    }

    $last = json_decode((string) end($transport->requests)->getBody(), true);
    expect(AssistantMessage::query()->where('member_id', $member->id)->count())->toBe(10)
        ->and($last['messages'][0]['role'])->toBe('user')
        // Ten stored turns (oldest dropped) plus the new question.
        ->and(collect($last['messages'])->pluck('role')->all())->toBe(['user', 'assistant', 'user', 'assistant', 'user', 'assistant', 'user', 'assistant', 'user', 'assistant', 'user'])
        ->and($last['messages'][0]['content'])->toBe('Question number 2 about my visa')
        ->and(lastMessage()['reply_markup'])->toBeNull();
});

test('without a key the FAQ answers in the member\'s language', function (): void {
    joinMember(502, '+971500000502', 'አማርኛ');

    say(502, 'bedspace Deira where find');

    expect(lastText())->toContain('ዴይራ ውስጥ አልጋ የት አገኛለሁ?')->toContain('በጥያቄ ቡድኑ');
});

test('with nothing to go on, the assistant says so and offers the community', function (): void {
    joinMember(503);

    say(503, 'What is the best football team?');

    expect(lastText())->toContain('do not have an answer')
        ->and(lastMessage()['reply_markup']['inline_keyboard'][0][0]['callback_data'])->toBe('q:fromai');
});

test('when Claude fails the FAQ still answers', function (): void {
    claudeWith(fakeTransport([[529, json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'busy']])]]));
    joinMember(504);

    say(504, 'Where can I find a bedspace in Deira?');

    expect(lastText())->toContain('Here is what the FAQ says')->toContain('Ask in the housing topic');
});

test('the assistant has a daily limit', function (): void {
    PlatformSetting::put('assistant_per_day', '1');
    joinMember(505);

    say(505, 'Where can I find a bedspace in Deira?');
    say(505, 'And in Satwa?');

    expect(lastText())->toContain('asked the assistant 1 times today');
});

test('"Ask the community" turns the last assistant question into a /ask', function (): void {
    PlatformSetting::put('telegram_questions_group_id', '-1009000');
    joinMember(506);
    say(506, 'Can I change my job without the sponsor agreeing to it?');

    pressQ(506, 'q:fromai');

    expect(lastText())->toContain('What is the question about?')
        ->and(\App\Models\Conversation::query()->where('telegram_user_id', 506)->first()->recall('body'))->toBe('Can I change my job without the sponsor agreeing to it?');
});

test('the Claude assistant is built with the stored key, not a bare client', function (): void {
    JobSettings::setAnthropicKey('sk-ant-api03-test-key-not-real-0000');

    $claude = app(ClaudeJson::class);
    $client = (fn () => $this->client)->call($claude);

    expect($client)->toBeNull();
});
