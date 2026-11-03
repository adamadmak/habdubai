<?php

use App\Models\Member;
use App\Models\User;
use App\Services\TelegramBotService;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // Nothing reaches Telegram: every Bot API call answers ok.
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1, 'username' => 'HabeshaDubaiBot']])]);
        \App\Bot\BotMessages::flush();
        \App\Models\PlatformSetting::flushValueCache();
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Telegram helpers
|--------------------------------------------------------------------------
|
| Build updates the way Telegram sends them and post them to the webhook.
| The queue is sync under test, so the handler runs inside the request.
|
*/

function webhookSecret(): string
{
    return app(TelegramBotService::class)->webhookSecret();
}

function nextUpdateId(): int
{
    static $id = 1000;

    return ++$id;
}

function telegramMessage(int $userId, array $message = [], array $from = []): array
{
    return [
        'update_id' => nextUpdateId(),
        'message' => array_merge([
            'message_id' => nextUpdateId(),
            'date' => time(),
            'chat' => ['id' => $userId, 'type' => 'private', 'first_name' => 'Abebe'],
            'from' => array_merge(['id' => $userId, 'is_bot' => false, 'first_name' => 'Abebe', 'language_code' => 'am'], $from),
        ], $message),
    ];
}

/** Post a text message from this user to the webhook. */
function say(int $userId, string $text, array $from = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/telegram/webhook/'.webhookSecret(), telegramMessage($userId, ['text' => $text], $from));
}

/** Share a contact card; `contactUserId` defaults to the sender, which is the honest case. */
function shareContact(int $userId, string $phone, ?int $contactUserId = null): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/telegram/webhook/'.webhookSecret(), telegramMessage($userId, [
        'contact' => ['phone_number' => $phone, 'first_name' => 'Abebe', 'user_id' => $contactUserId ?? $userId],
    ]));
}

/** @return array<int, array{chat_id: mixed, text: string, reply_markup: mixed}> every sendMessage the bot made */
function sentMessages(): array
{
    $sent = [];

    Http::recorded(function ($request) use (&$sent): void {
        if (str_ends_with($request->url(), '/sendMessage')) {
            $data = $request->data();
            $sent[] = ['chat_id' => $data['chat_id'] ?? null, 'text' => (string) ($data['text'] ?? ''), 'reply_markup' => $data['reply_markup'] ?? null];
        }
    });

    return $sent;
}

function lastMessage(): array
{
    $sent = sentMessages();

    return $sent === [] ? ['chat_id' => null, 'text' => '', 'reply_markup' => null] : end($sent);
}

function lastText(): string
{
    return lastMessage()['text'];
}

/** Walk a fresh user through the whole join flow; returns the member. */
function joinMember(int $userId, string $phone = '+971501234567', string $language = 'English'): Member
{
    say($userId, '/start');
    say($userId, $language);
    shareContact($userId, $phone);
    say($userId, 'Abebe K');
    say($userId, 'Dubai');
    say($userId, 'Driver');
    say($userId, '✅ Done');
    say($userId, '✅ I agree');

    return Member::findByTelegramUser($userId);
}

function admin(): User
{
    return User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'correct-horse-battery']);
}

function phoneHash(string $phone): string
{
    return Phone::hash(Phone::normalize($phone));
}
