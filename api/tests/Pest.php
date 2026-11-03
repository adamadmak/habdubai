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
        // Nothing reaches the network: every Bot API call answers ok, and any
        // other host must be faked by the test that calls it.
        Http::preventStrayRequests();
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

/*
|--------------------------------------------------------------------------
| Jobs helpers
|--------------------------------------------------------------------------
*/

/** Every Bot API call of one method, as [payload, ...]. */
function telegramCalls(string $method): array
{
    $calls = [];

    Http::recorded(function ($request) use (&$calls, $method): void {
        if (str_ends_with($request->url(), '/'.$method)) {
            $calls[] = $request->data();
        }
    });

    return $calls;
}

function jobsChannel(string $channel = '@habesha_jobs'): string
{
    \App\Models\PlatformSetting::put('job_channel_id', $channel);

    return $channel;
}

/** Bind a classifier that answers with these fields (merged over a plain driver job in Dubai). */
function fakeClassifier(array $fields = [], ?\Throwable $throw = null): void
{
    app()->instance(\App\Jobs\Pipeline\JobClassifier::class, new class($fields, $throw) implements \App\Jobs\Pipeline\JobClassifier
    {
        public int $calls = 0;

        public function __construct(private array $fields, private ?\Throwable $throw) {}

        public function classify(\App\Models\JobRaw $raw): \App\Jobs\Pipeline\Classification
        {
            $this->calls++;

            if ($this->throw) {
                throw $this->throw;
            }

            return \App\Jobs\Pipeline\Classification::fromArray($this->fields + [
                'is_job' => true, 'category' => 'driver', 'emirate' => 'dubai', 'title' => $raw->title ?? 'Driver',
                'company' => $raw->company ?? '', 'sub_area' => '', 'salary_min' => 2500, 'salary_max' => 3000, 'salary_currency' => 'AED',
                'accommodation' => 'yes', 'visa' => 'yes', 'languages' => ['English'],
                'summary_am' => 'ሹፌር በዱባይ', 'summary_ti' => 'ሹፌር ኣብ ዱባይ', 'summary_en' => 'A driver job in Dubai.',
                'scam_risk' => 0, 'scam_signals' => [],
            ], 'claude');
        }
    });
}

function jobSource(array $attributes = []): \App\Models\JobSource
{
    static $n = 0;
    $n++;

    return \App\Models\JobSource::query()->create($attributes + [
        'key' => 'source-'.$n, 'name' => 'Source '.$n, 'kind' => 'rss',
        'config' => ['url' => 'https://feeds.example.com/jobs-'.$n.'.xml'], 'interval_minutes' => 60, 'active' => true,
    ]);
}

/** Store a raw listing and run it through the pipeline (the queue is sync). */
function ingest(array $listing = [], ?\App\Models\JobSource $source = null): ?\App\Models\Job
{
    static $n = 0;
    $n++;

    $raw = app(\App\Jobs\Pipeline\JobIngest::class)->storeOne($source ?? jobSource(), new \App\Jobs\Sources\RawListing(
        externalKey: $listing['key'] ?? 'listing-'.$n,
        text: $listing['text'] ?? 'Light vehicle driver needed in Deira for a logistics company. Salary AED 2500 to 3000, accommodation provided, apply with CV. Listing '.$n.' details about shifts and routes.',
        url: array_key_exists('url', $listing) ? $listing['url'] : 'https://jobs.example.com/view/'.$n,
        title: $listing['title'] ?? 'Light Vehicle Driver '.$n,
        company: $listing['company'] ?? 'Fast Logistics',
        location: $listing['location'] ?? 'Dubai',
    ));

    return $raw ? \App\Models\Job::query()->where('raw_id', $raw->id)->first() : null;
}

/** A canned Messages API response body. */
function claudeResponse(array $json, string $stopReason = 'end_turn', ?array $stopDetails = null): string
{
    return json_encode([
        'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
        'content' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'], ['type' => 'text', 'text' => json_encode($json)]],
        'stop_reason' => $stopReason, 'stop_sequence' => null, 'stop_details' => $stopDetails,
        'usage' => ['input_tokens' => 900, 'output_tokens' => 400, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0],
    ]);
}

/** A PSR-18 client that answers every request with the given responses in turn and keeps what it was sent. */
function fakeTransport(array $responses): object
{
    return new class($responses) implements \Psr\Http\Client\ClientInterface
    {
        public array $requests = [];

        public function __construct(private array $responses) {}

        public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            $this->requests[] = $request;
            [$status, $body] = array_shift($this->responses) ?? [500, '{}'];

            return new \GuzzleHttp\Psr7\Response($status, ['Content-Type' => 'application/json', 'request-id' => 'req_test'], $body);
        }
    };
}
