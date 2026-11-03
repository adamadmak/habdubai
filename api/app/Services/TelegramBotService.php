<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Support\RuntimeEnv;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The one door to Telegram's Bot API. The token comes from config, then the
 * value the admin saved (encrypted in platform_settings), then the process
 * environment; anything not shaped like a token is skipped, never sent.
 */
class TelegramBotService
{
    public const STORED_TOKEN_KEY = 'telegram_bot_token_encrypted';

    public const STORED_USERNAME_KEY = 'telegram_bot_username';

    public const TOKEN_PATTERN = '/^\d{6,}:[A-Za-z0-9_-]{20,}$/';

    public const USERNAME_PATTERN = '/^[A-Za-z][A-Za-z0-9_]{4,31}$/';

    public const WEBHOOK_SECRET_PATTERN = '/^[A-Za-z0-9_-]{16,256}$/';

    public function configured(): bool
    {
        return $this->botToken() !== '';
    }

    public function botUsername(): string
    {
        foreach ([
            PlatformSetting::valueFor(self::STORED_USERNAME_KEY, ''),
            config('services.telegram.bot_username'),
        ] as $candidate) {
            $candidate = ltrim(trim((string) $candidate), '@');
            if (preg_match(self::USERNAME_PATTERN, $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    public static function isToken(string $value): bool
    {
        return (bool) preg_match(self::TOKEN_PATTERN, trim($value));
    }

    /** Verify a token with getMe, then keep it (encrypted) and the bot's username. */
    public function configureToken(string $token): array
    {
        $token = trim($token);

        if (! self::isToken($token)) {
            throw new RuntimeException('Enter a valid Telegram bot token from BotFather.');
        }

        try {
            $response = $this->http()->asJson()->timeout(10)->post('https://api.telegram.org/bot'.$token.'/getMe');
        } catch (ConnectionException) {
            throw new RuntimeException('Telegram could not be reached to verify the bot token. Try again shortly.');
        }

        $bot = $this->validated($response)->json('result', []);

        PlatformSetting::put(self::STORED_TOKEN_KEY, Crypt::encryptString($token));

        if (filled($bot['username'] ?? null)) {
            PlatformSetting::put(self::STORED_USERNAME_KEY, ltrim((string) $bot['username'], '@'));
        }

        return $bot;
    }

    public function getMe(): array
    {
        return $this->post('getMe')->json('result', []);
    }

    public function getWebhookInfo(): array
    {
        return $this->post('getWebhookInfo')->json('result', []);
    }

    /** Long polling, for local development only (bot:poll). Fails while a webhook is set. */
    public function getUpdates(int $offset, int $timeout = 25): array
    {
        return $this->post('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => ['message', 'edited_message', 'channel_post', 'callback_query', 'my_chat_member'],
        ], retry: false, timeout: $timeout + 10)->json('result', []);
    }

    public function setWebhook(string $url): array
    {
        return $this->post('setWebhook', [
            'url' => $url,
            'secret_token' => $this->webhookSecret(),
            'allowed_updates' => ['message', 'edited_message', 'channel_post', 'callback_query', 'my_chat_member', 'chat_member'],
        ], retry: false)->json();
    }

    /** The secret Telegram sends back in every webhook call; derived from the token unless the operator set one. */
    public function webhookSecret(): string
    {
        $configured = trim(RuntimeEnv::get('services.telegram.webhook_secret', 'telegram_webhook_secret', 'TELEGRAM_WEBHOOK_SECRET'));

        if (preg_match(self::WEBHOOK_SECRET_PATTERN, $configured)) {
            return $configured;
        }

        $token = $this->botToken();

        if ($token === '') {
            throw new RuntimeException('The Telegram bot token is not configured.');
        }

        return hash('sha256', 'habesha-dubai-webhook|'.$token);
    }

    public function validWebhookSecret(string $provided): bool
    {
        if (! preg_match(self::WEBHOOK_SECRET_PATTERN, $provided)) {
            return false;
        }

        try {
            return hash_equals($this->webhookSecret(), $provided);
        } catch (RuntimeException) {
            return false;
        }
    }

    public function sendText(int|string $chatId, string $text, ?array $replyMarkup = null): array
    {
        return $this->post('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            'reply_markup' => $replyMarkup,
        ], fn ($value) => $value !== null))->json('result', []);
    }

    /** A photo already on Telegram (by file_id), with an HTML caption of at most 1024 characters. */
    public function sendPhotoById(int|string $chatId, string $fileId, string $caption = '', ?array $replyMarkup = null): array
    {
        return $this->post('sendPhoto', array_filter([
            'chat_id' => $chatId,
            'photo' => $fileId,
            'caption' => $caption !== '' ? mb_substr($caption, 0, 1024) : null,
            'parse_mode' => $caption !== '' ? 'HTML' : null,
            'reply_markup' => $replyMarkup,
        ], fn ($value) => $value !== null))->json('result', []);
    }

    /** Take the inline buttons off a message once they have been used, keeping its text. */
    public function clearButtons(int|string $chatId, int $messageId): bool
    {
        try {
            $this->post('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => ['inline_keyboard' => []]], retry: false, timeout: 5);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function editCaption(int|string $chatId, int $messageId, string $caption): bool
    {
        try {
            $this->post('editMessageCaption', ['chat_id' => $chatId, 'message_id' => $messageId, 'caption' => mb_substr($caption, 0, 1024), 'parse_mode' => 'HTML'], retry: false);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function pin(int|string $chatId, int $messageId): bool
    {
        try {
            $this->post('pinChatMessage', ['chat_id' => $chatId, 'message_id' => $messageId, 'disable_notification' => false], retry: false);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function unpin(int|string $chatId, int $messageId): bool
    {
        try {
            $this->post('unpinChatMessage', ['chat_id' => $chatId, 'message_id' => $messageId], retry: false);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /** Stop a member posting in a group until the given time; Telegram lifts it by itself. */
    public function mute(int|string $chatId, int $userId, \DateTimeInterface $until): bool
    {
        try {
            $this->post('restrictChatMember', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'permissions' => [
                    'can_send_messages' => false, 'can_send_audios' => false, 'can_send_documents' => false,
                    'can_send_photos' => false, 'can_send_videos' => false, 'can_send_video_notes' => false,
                    'can_send_voice_notes' => false, 'can_send_polls' => false, 'can_send_other_messages' => false,
                    'can_add_web_page_previews' => false,
                ],
                'use_independent_chat_permissions' => true,
                'until_date' => $until->getTimestamp(),
            ], retry: false);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /** "typing…" in the chat while something slow runs. Best effort. */
    public function typing(int|string $chatId): void
    {
        try {
            $this->post('sendChatAction', ['chat_id' => $chatId, 'action' => 'typing'], retry: false, timeout: 5);
        } catch (RuntimeException) {
        }
    }

    /** A message in a group, optionally inside a forum topic. */
    public function sendToGroup(int|string $chatId, string $text, ?int $threadId = null, ?array $replyMarkup = null, ?int $replyTo = null): array
    {
        return $this->post('sendMessage', array_filter([
            'chat_id' => $chatId,
            'message_thread_id' => $threadId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            'reply_markup' => $replyMarkup,
            'reply_parameters' => $replyTo ? ['message_id' => $replyTo, 'allow_sending_without_reply' => true] : null,
        ], fn ($value) => $value !== null))->json('result', []);
    }

    /** A new forum topic; returns its message_thread_id, or null when the group has no topics. */
    public function createForumTopic(int|string $chatId, string $name): ?int
    {
        try {
            $result = $this->post('createForumTopic', ['chat_id' => $chatId, 'name' => mb_substr($name, 0, 128)], retry: false)->json('result', []);

            return isset($result['message_thread_id']) ? (int) $result['message_thread_id'] : null;
        } catch (RuntimeException) {
            return null;
        }
    }

    public function editForumTopic(int|string $chatId, int $threadId, string $name): bool
    {
        try {
            $this->post('editForumTopic', ['chat_id' => $chatId, 'message_thread_id' => $threadId, 'name' => mb_substr($name, 0, 128)], retry: false);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function closeForumTopic(int|string $chatId, int $threadId): bool
    {
        try {
            $this->post('closeForumTopic', ['chat_id' => $chatId, 'message_thread_id' => $threadId], retry: false);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /** A map pin with a title and address, which Telegram shows as a tappable place. */
    public function sendVenue(int|string $chatId, float $latitude, float $longitude, string $title, string $address, ?array $replyMarkup = null): array
    {
        return $this->post('sendVenue', array_filter([
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => mb_substr($title, 0, 120),
            'address' => mb_substr($address, 0, 200),
            'reply_markup' => $replyMarkup,
        ], fn ($value) => $value !== null), retry: false)->json('result', []);
    }

    /** One attempt, five seconds: for a notice sent inside somebody else's request. */
    public function notify(int|string $chatId, string $text): void
    {
        $this->post('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], retry: false, timeout: 5);
    }

    /** Replace a message's text and inline keyboard. False when Telegram refuses (too old, unchanged, deleted). */
    public function editText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null): bool
    {
        try {
            $this->post('editMessageText', array_filter([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'link_preview_options' => ['is_disabled' => true],
                'reply_markup' => $replyMarkup,
            ], fn ($value) => $value !== null), retry: false);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /** Stop the button's spinner; a short text shows as a toast, `url` opens a t.me link. */
    public function answerCallback(string $callbackId, ?string $text = null, bool $alert = false, ?string $url = null): void
    {
        try {
            $this->post('answerCallbackQuery', array_filter([
                'callback_query_id' => $callbackId,
                'text' => $text !== null ? mb_substr($text, 0, 190) : null,
                'show_alert' => $alert ?: null,
                'url' => $url,
            ], fn ($value) => $value !== null), retry: false, timeout: 5);
        } catch (RuntimeException) {
            // An expired callback cannot be answered; the action itself already ran.
        }
    }

    /** t.me link that opens the bot with a /start payload. */
    public function startLink(string $payload = ''): ?string
    {
        $username = $this->botUsername();

        return $username === '' ? null : 'https://t.me/'.$username.($payload !== '' ? '?start='.rawurlencode($payload) : '');
    }

    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        try {
            return (bool) $this->post('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId], retry: false)->json('result', false);
        } catch (RuntimeException) {
            return false;
        }
    }

    /** Remove a user from a group without a lasting ban (ban, then unban). */
    public function kickFromChat(int|string $chatId, int $userId): bool
    {
        try {
            $this->post('banChatMember', ['chat_id' => $chatId, 'user_id' => $userId], retry: false);
            $this->post('unbanChatMember', ['chat_id' => $chatId, 'user_id' => $userId, 'only_if_banned' => true], retry: false);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function banFromChat(int|string $chatId, int $userId): bool
    {
        try {
            $this->post('banChatMember', ['chat_id' => $chatId, 'user_id' => $userId], retry: false);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function contactKeyboard(string $label): array
    {
        return [
            'keyboard' => [[['text' => $label, 'request_contact' => true]]],
            'resize_keyboard' => true,
            'is_persistent' => true,
            'one_time_keyboard' => false,
        ];
    }

    /** @param array<int, array<int, string>> $rows */
    public function replyKeyboard(array $rows, bool $oneTime = true): array
    {
        return [
            'keyboard' => array_map(fn (array $row) => array_map(fn (string $label) => ['text' => $label], $row), $rows),
            'resize_keyboard' => true,
            'one_time_keyboard' => $oneTime,
        ];
    }

    public function removeKeyboard(): array
    {
        return ['remove_keyboard' => true];
    }

    private function post(string $method, array $payload = [], bool $retry = true, ?int $timeout = null): Response
    {
        try {
            $request = $this->http()->asJson()->timeout($timeout ?? (int) config('services.telegram.timeout', 20));

            if ($retry) {
                $request = $request->retry(2, 300, throw: false);
            }

            $response = $request->post($this->endpoint($method), $payload);
        } catch (ConnectionException) {
            throw new RuntimeException('Telegram API could not be reached. Try again shortly.');
        }

        return $this->validated($response);
    }

    private function validated(Response $response): Response
    {
        if (! $response->successful() || ! $response->json('ok', false)) {
            $description = (string) $response->json('description', 'Telegram request failed.');

            throw new RuntimeException(str($description)->limit(300)->toString());
        }

        return $response;
    }

    private function endpoint(string $method): string
    {
        $token = $this->botToken();

        if ($token === '') {
            throw new RuntimeException('The Telegram bot token is not configured.');
        }

        return 'https://api.telegram.org/bot'.$token.'/'.$method;
    }

    private function botToken(): string
    {
        foreach ([
            fn () => (string) config('services.telegram.bot_token'),
            fn () => $this->storedToken(),
            fn () => (string) (getenv('TELEGRAM_BOT_TOKEN') ?: ''),
            fn () => RuntimeEnv::fromEnvFile('TELEGRAM_BOT_TOKEN'),
        ] as $source) {
            $candidate = trim($source());
            if (self::isToken($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function storedToken(): string
    {
        $encrypted = trim((string) PlatformSetting::valueFor(self::STORED_TOKEN_KEY, ''));

        if ($encrypted === '') {
            return '';
        }

        try {
            return trim(Crypt::decryptString($encrypted));
        } catch (\Throwable) {
            return '';
        }
    }

    private function http(): PendingRequest
    {
        return Http::withOptions(['verify' => \App\Support\TrustedCas::path()])->connectTimeout(5);
    }
}
