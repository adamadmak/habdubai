<?php

use Anthropic\Client;
use App\Ai\ClaudeJson;
use App\Models\BotChat;
use App\Models\EmployerReport;
use App\Models\FaqEntry;
use App\Models\KnowledgeArticle;
use App\Models\PlatformSetting;
use App\Models\Question;
use App\Models\QuestionReply;
use App\Support\Community;
use App\Support\JobSettings;
use Laravel\Sanctum\Sanctum;

function solvedQuestion(): Question
{
    $asker = joinMember(600, '+971500000600');
    $helper = joinMember(601, '+971500000601');
    $q = Question::query()->create(['member_id' => $asker->id, 'category' => 'visa', 'title' => 'Renewal fee', 'body' => 'Who pays the renewal fee for my residence visa?', 'group_chat_id' => -1009000, 'group_message_id' => 5, 'status' => 'open', 'last_activity_at' => now()]);
    $reply = QuestionReply::query()->create(['question_id' => $q->id, 'member_id' => $helper->id, 'telegram_message_id' => 77, 'text' => 'The employer pays it; complain to MoHRE if they ask you.', 'points_awarded' => 1, 'created_at' => now()]);
    $helper->forceFill(['helper_points' => 1])->save();

    return $q;
}

test('community admin routes deny a guest', function (string $method, string $path): void {
    $this->json($method, $path)->assertStatus(401);
})->with([
    ['GET', '/api/admin/questions'],
    ['GET', '/api/admin/faq'],
    ['POST', '/api/admin/knowledge'],
    ['GET', '/api/admin/employer-reports'],
]);

test('a moderator marks the solution from the dashboard', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    $q = solvedQuestion();
    $reply = QuestionReply::query()->first();

    $this->getJson('/api/admin/questions')->assertOk()->assertJsonPath('data.0.replies_count', 1)->assertJsonPath('counts.open', 1);
    $this->getJson('/api/admin/questions/'.$q->id)->assertOk()->assertJsonPath('question.replies.0.text', $reply->text);

    $this->postJson('/api/admin/questions/'.$q->id.'/solve', ['reply_id' => $reply->id])->assertOk()->assertJsonPath('question.status', 'solved');
    expect($reply->member->fresh()->helper_points)->toBe(6);

    $this->postJson('/api/admin/questions/'.$q->id.'/solve', ['reply_id' => $reply->id])->assertStatus(422);
});

test('promoting without a key makes an inactive English draft and gives the answerer 10 points', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    $q = solvedQuestion();
    $this->postJson('/api/admin/questions/'.$q->id.'/promote')->assertStatus(422);

    $this->postJson('/api/admin/questions/'.$q->id.'/solve', ['reply_id' => QuestionReply::query()->value('id')])->assertOk();
    $this->postJson('/api/admin/questions/'.$q->id.'/promote')->assertCreated()->assertJsonPath('written_by', 'copy');

    $faq = FaqEntry::query()->firstOrFail();
    expect($faq->active)->toBeFalse()->and($faq->q_en)->toBe($q->body)->and($faq->q_am)->toBeNull()
        ->and($q->fresh()->faq_id)->toBe($faq->id)
        ->and(QuestionReply::query()->first()->member->fresh()->helper_points)->toBe(16);

    $this->postJson('/api/admin/questions/'.$q->id.'/promote')->assertStatus(422);
});

test('promoting with Claude writes the entry in three languages and lists it', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    JobSettings::setAnthropicKey('sk-ant-api03-test-key-not-real-0000');
    $transport = fakeTransport([[200, claudeResponse(['q_am' => 'የቪዛ እድሳት ክፍያ ማን ይከፍላል?', 'q_ti' => 'ክፍሊት ምሕዳስ ቪዛ መን ይኸፍል?', 'q_en' => 'Who pays for a residence visa renewal?', 'a_am' => 'አሠሪው።', 'a_ti' => 'ኣስራሒ።', 'a_en' => 'The employer pays.'])]]);
    app()->instance(ClaudeJson::class, new ClaudeJson(new Client(apiKey: 'sk-ant-test', requestOptions: ['transporter' => $transport, 'maxRetries' => 0])));

    $q = solvedQuestion();
    $this->postJson('/api/admin/questions/'.$q->id.'/solve', ['reply_id' => QuestionReply::query()->value('id')]);
    $this->postJson('/api/admin/questions/'.$q->id.'/promote')->assertCreated()->assertJsonPath('written_by', 'claude');

    $faq = FaqEntry::query()->firstOrFail();
    expect($faq->active)->toBeTrue()->and($faq->q_am)->toBe('የቪዛ እድሳት ክፍያ ማን ይከፍላል?')
        ->and(json_decode((string) $transport->requests[0]->getBody(), true)['messages'][0]['content'])->toContain('<accepted_answer>');
});

test('FAQ entries and knowledge articles are managed from the dashboard', function (): void {
    Sanctum::actingAs(admin(), ['admin']);

    $this->postJson('/api/admin/faq', ['category' => 'visa'])->assertStatus(422)->assertJsonValidationErrors(['q_en', 'a_en']);
    $id = $this->postJson('/api/admin/faq', ['category' => 'visa', 'q_en' => 'Q?', 'a_en' => 'A.', 'active' => true])->assertCreated()->json('faq.id');
    $this->putJson('/api/admin/faq/'.$id, ['category' => 'work', 'q_en' => 'Q2?', 'a_en' => 'A2.', 'q_am' => 'ጥ?', 'active' => false])->assertOk()->assertJsonPath('faq.q_am', 'ጥ?');

    $aid = $this->postJson('/api/admin/knowledge', ['title' => 'Passports', 'body' => 'Employers may not keep a worker\'s passport.', 'source_name' => 'MoHRE', 'source_url' => 'https://www.mohre.gov.ae/', 'active' => true])
        ->assertCreated()->json('article.id');

    $this->getJson('/api/admin/faq')->assertOk()->assertJsonPath('faq.0.q_en', 'Q2?')->assertJsonPath('knowledge.0.title', 'Passports');

    $this->deleteJson('/api/admin/faq/'.$id)->assertOk();
    $this->deleteJson('/api/admin/knowledge/'.$aid)->assertOk();
    expect(FaqEntry::query()->count() + KnowledgeArticle::query()->count())->toBe(0);
});

test('employer reports show the reporter only when they agreed to be contacted', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    $m = joinMember(610);
    EmployerReport::query()->create(['member_id' => $m->id, 'employer_name' => 'House A', 'text' => 'No salary for months.', 'contactable' => true]);
    EmployerReport::query()->create(['member_id' => $m->id, 'employer_name' => 'house a ', 'text' => 'Same employer again.', 'contactable' => false]);

    $data = $this->getJson('/api/admin/employer-reports')->assertOk()->json('data');
    expect($data[0]['member'])->toBeNull()->and($data[1]['member']['name'])->toBe('Abebe K')
        ->and($data[0]['times_named'])->toBe(2);

    $this->putJson('/api/admin/employer-reports/'.$data[0]['id'], ['status' => 'closed', 'note' => 'Referred to MoHRE'])->assertOk();
    $this->getJson('/api/admin/employer-reports')->assertJsonCount(1, 'data');
});

test('a group the bot is added to shows up on the settings page with its id', function (): void {
    Sanctum::actingAs(admin(), ['admin']);

    $this->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'my_chat_member' => [
            'chat' => ['id' => -1001234, 'type' => 'supergroup', 'title' => 'Habesha Dubai Q&A', 'is_forum' => true],
            'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'Owner'],
            'date' => time(),
            'old_chat_member' => ['status' => 'left', 'user' => ['id' => 2, 'is_bot' => true, 'first_name' => 'Bot']],
            'new_chat_member' => ['status' => 'administrator', 'user' => ['id' => 2, 'is_bot' => true, 'first_name' => 'Bot']],
        ],
    ])->assertOk();

    expect(BotChat::query()->first()->is_forum)->toBeTrue();
    $this->getJson('/api/admin/settings')->assertOk()
        ->assertJsonPath('chats.0.chat_id', -1001234)->assertJsonPath('chats.0.title', 'Habesha Dubai Q&A')
        ->assertJsonPath('settings.community_language', 'am')->assertJsonPath('settings.assistant_per_day', '20');
});

test('a group Telegram upgrades keeps its place on the settings page and in the settings, under its new id', function (): void {
    BotChat::query()->create(['chat_id' => -4001, 'type' => 'group', 'title' => 'Q&A', 'is_forum' => false, 'bot_status' => 'administrator']);
    PlatformSetting::put('telegram_questions_group_id', '-4001');
    PlatformSetting::put('telegram_goods_group_id', '-4002');

    // Telegram says so in the old chat, then in the new one, which now has a public name and Topics.
    $this->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'message' => ['message_id' => 9, 'date' => time(), 'chat' => ['id' => -4001, 'type' => 'group', 'title' => 'Q&A'], 'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'Owner'], 'migrate_to_chat_id' => -1009001],
    ])->assertOk();
    $this->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'message' => ['message_id' => 1, 'date' => time(), 'chat' => ['id' => -1009001, 'type' => 'supergroup', 'title' => 'Q&A', 'username' => 'habuae_qa', 'is_forum' => true], 'migrate_from_chat_id' => -4001],
    ])->assertOk();

    $chat = BotChat::query()->sole();
    expect($chat->chat_id)->toBe(-1009001)
        ->and($chat->type)->toBe('supergroup')
        ->and($chat->username)->toBe('habuae_qa')
        ->and($chat->is_forum)->toBeTrue()
        ->and($chat->bot_status)->toBe('administrator')
        ->and(Community::questionsGroup())->toBe('-1009001')
        ->and(Community::goodsGroup())->toBe('-4002');
});

test('quiet questions are closed after seven days', function (): void {
    $q = solvedQuestion();
    $q->forceFill(['forum_topic_id' => 55, 'last_activity_at' => now()->subDays(8)])->save();

    $this->artisan('questions:close-quiet')->assertSuccessful();

    expect($q->fresh()->status)->toBe('closed')
        ->and(telegramCalls('closeForumTopic')[0]['message_thread_id'])->toBe(55);
});
