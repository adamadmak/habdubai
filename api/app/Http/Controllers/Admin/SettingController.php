<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\TelegramUpdate;
use App\Services\TelegramBotService;
use App\Support\RuntimeEnv;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Operator settings. The bot token is write-only: it is verified with
 * Telegram, stored encrypted, and never read back to the page.
 */
class SettingController extends Controller
{
    /** @var array<string, array<int, string>> key => validation rules */
    private const FIELDS = [
        // Links members and the website open: web addresses only, never javascript: or data:.
        'public_url' => ['nullable', 'url:http,https', 'max:200'],
        'site_url' => ['nullable', 'url:http,https', 'max:200'],
        // A Telegram username (it becomes https://t.me/<name> on the website).
        'support_telegram_username' => ['nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z][A-Za-z0-9_]{3,31}$/'],
        // The site's chat button. With the country code: wa.me cannot open 050 123 4567.
        'whatsapp_contact_number' => ['nullable', 'string', 'max:24', 'regex:/^\+?[1-9](?:[\s\-]?\d){6,14}$/'],
        // Country calling codes, comma separated: 971,251.
        'allowed_phone_countries' => ['nullable', 'string', 'max:100', 'regex:/^\s*\d{1,4}(\s*,\s*\d{1,4})*\s*$/'],
        'telegram_channel_link' => ['nullable', 'url:http,https', 'max:200'],
        'telegram_questions_group_link' => ['nullable', 'url:http,https', 'max:200'],
        'telegram_goods_group_link' => ['nullable', 'url:http,https', 'max:200'],
        // A group's numeric id (-100…): a typo here sends every group message nowhere.
        'telegram_questions_group_id' => ['nullable', 'string', 'max:32', 'regex:/^-?\d{4,20}$/'],
        'telegram_goods_group_id' => ['nullable', 'string', 'max:32', 'regex:/^-?\d{4,20}$/'],
        'community_language' => ['nullable', 'in:am,ti,en,om'],
        'questions_per_day' => ['nullable', 'integer', 'min:1', 'max:50'],
        'assistant_per_day' => ['nullable', 'integer', 'min:0', 'max:500'],
        'safety_consulate_phone' => ['nullable', 'string', 'max:40'],
        'safety_mohre_phone' => ['nullable', 'string', 'max:40'],
        // Shared with the public website (its TICK_TOKEN and SITE_API_KEY). Environment variables
        // never reach this app on the host, so they are set here.
        'tick_token' => ['nullable', 'string', 'min:32', 'max:128'],
        'site_api_key' => ['nullable', 'string', 'min:32', 'max:128'],
    ];

    /** Write-only: never sent back to the page; a blank box keeps the stored value. */
    public const SECRETS = ['tick_token', 'site_api_key'];

    /** Shown when nothing is saved, so the form says what the bot actually uses. */
    private const DEFAULTS = [
        'allowed_phone_countries' => '971,251',
        'community_language' => 'am',
        'questions_per_day' => '5',
        'assistant_per_day' => '20',
    ];

    public function show(TelegramBotService $telegram): JsonResponse
    {
        $settings = [];
        $secrets = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $value = RuntimeEnv::get(null, $key, null, self::DEFAULTS[$key] ?? '');
            if (in_array($key, self::SECRETS, true)) {
                $secrets[$key] = $value !== '';
                $value = '';
            }
            $settings[$key] = $value;
        }

        return response()->json([
            'secrets' => $secrets,
            'settings' => $settings,
            'telegram' => $this->telegramState($telegram),
            // Groups and channels the bot has been added to, to pick from instead of typing ids.
            'chats' => \App\Models\BotChat::query()->whereIn('bot_status', ['administrator', 'member', 'creator'])->latest('updated_at')->get(['chat_id', 'type', 'title', 'username', 'is_forum', 'bot_status']),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(self::FIELDS, [
            'telegram_questions_group_id.regex' => 'Use the group\'s numeric id (-100…); pick it from the list of chats the bot is in.',
            'telegram_goods_group_id.regex' => 'Use the group\'s numeric id (-100…); pick it from the list of chats the bot is in.',
            'support_telegram_username.regex' => 'Use a Telegram username, like @habesha_support.',
            'allowed_phone_countries.regex' => 'Use country calling codes separated by commas, like 971,251.',
            'whatsapp_contact_number.regex' => 'Use the full number with its country code, like +971 50 123 4567.',
        ]);

        $before = [];
        $after = [];
        foreach ($data as $key => $value) {
            $secret = in_array($key, self::SECRETS, true);
            if ($secret && trim((string) $value) === '') {
                continue; // A blank secret box keeps what is stored.
            }
            $before[$key] = $secret ? '(hidden)' : RuntimeEnv::get(null, $key, null);
            $after[$key] = $secret ? '(changed)' : $value;
            // Secrets are stored encrypted, like the bot token: a copy of the database alone does not hold them.
            PlatformSetting::put($key, $secret ? RuntimeEnv::conceal(trim((string) $value)) : trim((string) $value));
        }

        AuditLog::record($request->user()->id, 'settings.updated', 'settings', null, $before, $after);

        return $this->show(app(TelegramBotService::class));
    }

    /** Save a new bot token (verified with getMe) and register the webhook with it. */
    public function telegram(Request $request, TelegramBotService $telegram): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);

        try {
            $bot = $telegram->configureToken($data['token']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // The token is saved either way; the webhook needs a public URL, which a
        // local machine does not have (use `php artisan bot:poll` there).
        $webhook = null;
        $webhookError = null;
        try {
            $webhook = $this->registerWebhook($telegram);
        } catch (RuntimeException $e) {
            $webhookError = $e->getMessage();
        }

        AuditLog::record($request->user()->id, 'telegram.token_set', 'settings', null, null, ['username' => $bot['username'] ?? null, 'webhook' => $webhookError === null]);

        return response()->json(['bot' => $bot, 'webhook' => $webhook, 'webhook_error' => $webhookError, 'telegram' => $this->telegramState($telegram)]);
    }

    public function webhook(Request $request, TelegramBotService $telegram): JsonResponse
    {
        try {
            $webhook = $this->registerWebhook($telegram);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLog::record($request->user()->id, 'telegram.webhook_set');

        return response()->json(['webhook' => $webhook, 'telegram' => $this->telegramState($telegram)]);
    }

    private function registerWebhook(TelegramBotService $telegram): array
    {
        // The admin's value first: APP_URL on the host is whatever the build had.
        $publicUrl = rtrim(RuntimeEnv::get(null, 'public_url', 'APP_URL', (string) config('app.url')), '/');

        if ($publicUrl === '' || str_contains($publicUrl, 'localhost')) {
            throw new RuntimeException('Set the public URL first; Telegram cannot reach localhost.');
        }

        return $telegram->setWebhook($publicUrl.'/api/telegram/webhook/'.$telegram->webhookSecret());
    }

    private function telegramState(TelegramBotService $telegram): array
    {
        $state = ['configured' => $telegram->configured(), 'token_unreadable' => $telegram->tokenIsUnreadable(), 'username' => $telegram->botUsername(), 'webhook' => null];

        if ($state['configured']) {
            try {
                $info = $telegram->getWebhookInfo();
                $lastError = isset($info['last_error_date']) ? (int) $info['last_error_date'] : null;
                $received = TelegramUpdate::query()->max('received_at');
                $state['webhook'] = [
                    'url' => preg_replace('#/webhook/[^/]+$#', '/webhook/…', (string) ($info['url'] ?? '')),
                    'pending_update_count' => $info['pending_update_count'] ?? 0,
                    'last_error_message' => $info['last_error_message'] ?? null,
                    'last_error_date' => $lastError !== null ? date('c', $lastError) : null,
                    // Telegram keeps its last error on show long after deliveries recovered.
                    'last_error_recovered' => $lastError !== null && $received !== null && Carbon::parse($received)->timestamp > $lastError,
                ];
            } catch (\Throwable $e) {
                $state['webhook'] = ['error' => $e->getMessage()];
            }
        }

        return $state;
    }
}
