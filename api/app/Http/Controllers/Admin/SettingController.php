<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Services\TelegramBotService;
use App\Support\RuntimeEnv;
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
        'public_url' => ['nullable', 'url', 'max:200'],
        'site_url' => ['nullable', 'url:http,https', 'max:200'],
        'support_telegram_username' => ['nullable', 'string', 'max:40'],
        'allowed_phone_countries' => ['nullable', 'string', 'max:100'],
        'telegram_channel_link' => ['nullable', 'url', 'max:200'],
        'telegram_questions_group_link' => ['nullable', 'url', 'max:200'],
        'telegram_goods_group_link' => ['nullable', 'url', 'max:200'],
        'telegram_questions_group_id' => ['nullable', 'string', 'max:32'],
        'telegram_goods_group_id' => ['nullable', 'string', 'max:32'],
        'community_language' => ['nullable', 'in:am,ti,en'],
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
        $data = $request->validate(self::FIELDS);

        $before = [];
        $after = [];
        foreach ($data as $key => $value) {
            $secret = in_array($key, self::SECRETS, true);
            if ($secret && trim((string) $value) === '') {
                continue; // A blank secret box keeps what is stored.
            }
            $before[$key] = $secret ? '(hidden)' : RuntimeEnv::get(null, $key, null);
            $after[$key] = $secret ? '(changed)' : $value;
            PlatformSetting::put($key, trim((string) $value));
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
        $state = ['configured' => $telegram->configured(), 'username' => $telegram->botUsername(), 'webhook' => null];

        if ($state['configured']) {
            try {
                $info = $telegram->getWebhookInfo();
                $state['webhook'] = [
                    'url' => preg_replace('#/webhook/[^/]+$#', '/webhook/…', (string) ($info['url'] ?? '')),
                    'pending_update_count' => $info['pending_update_count'] ?? 0,
                    'last_error_message' => $info['last_error_message'] ?? null,
                    'last_error_date' => isset($info['last_error_date']) ? date('c', (int) $info['last_error_date']) : null,
                ];
            } catch (\Throwable $e) {
                $state['webhook'] = ['error' => $e->getMessage()];
            }
        }

        return $state;
    }
}
