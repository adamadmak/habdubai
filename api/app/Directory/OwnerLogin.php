<?php

namespace App\Directory;

use App\Bot\BotMessages;
use App\Bot\Callback;
use App\Models\AuditLog;
use App\Models\BusinessOwner;
use App\Models\Member;
use App\Models\OwnerLoginRequest;
use App\Services\TelegramBotService;
use App\Support\PublicUrls;
use Illuminate\Support\Str;

/**
 * "Continue with Telegram" on the website.
 *
 *   website → start(): a ref for the bot link, and a secret only the website's server keeps
 *   bot     → prompt(): /start login_<ref> asks "sign in to the website?"
 *   bot     → answer(): the person who opened the link says yes, and gets a six-digit code
 *   website → poll(): learns the person said yes, and asks them for the code
 *   website → verify(): with the secret and the code, gets an owner token exactly once
 *
 * The code goes from Telegram to the website, never the other way, so a
 * sign-in started by someone else and sent to an owner as a link gives that
 * someone nothing: the code arrives in the owner's chat, not on their screen.
 * Five wrong codes end the attempt.
 */
class OwnerLogin
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
    ) {}

    /** @return array{ref: string, secret: string, link: ?string, expires_at: string} */
    public function start(?string $ip, ?string $userAgent): array
    {
        OwnerLoginRequest::query()->where('expires_at', '<', now()->subDay())->delete();

        $ref = Str::random(24);
        $secret = Str::random(48);

        $request = OwnerLoginRequest::query()->create([
            'ref' => $ref,
            'secret_hash' => OwnerLoginRequest::hashSecret($secret),
            'check_code' => '0000',
            'status' => 'pending',
            'ip' => $ip ? mb_substr($ip, 0, 45) : null,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 200) : null,
            'expires_at' => now()->addMinutes(OwnerLoginRequest::LIFETIME_MINUTES),
        ]);

        return [
            'ref' => $ref,
            'secret' => $secret,
            'link' => $this->telegram->startLink('login_'.$ref),
            'expires_at' => $request->expires_at->toIso8601String(),
        ];
    }

    /** The bot was opened with /start login_<ref>. */
    public function prompt(int $telegramUserId, int $chatId, string $ref, string $language): void
    {
        $request = OwnerLoginRequest::query()->where('ref', $ref)->first();

        $taken = $request?->telegram_user_id !== null && (int) $request->telegram_user_id !== $telegramUserId;
        if (! $request || $request->status !== 'pending' || $request->isExpired() || $taken) {
            $this->telegram->sendText($chatId, $this->messages->text('login_expired', $language));

            return;
        }

        $request->forceFill(['telegram_user_id' => $telegramUserId])->save();

        $site = PublicUrls::site();
        $this->telegram->sendText($chatId, $this->messages->text('login_prompt', $language, [
            'site' => e($site !== '' ? (string) parse_url($site, PHP_URL_HOST) : $this->messages->text('login_site_fallback', $language)),
            'device' => e($this->device($request->user_agent)),
        ]), ['inline_keyboard' => [
            [['text' => $this->messages->button('btn_login_yes', $language), 'callback_data' => 'lg:ok:'.$request->id]],
            [['text' => $this->messages->button('btn_login_no', $language), 'callback_data' => 'lg:no:'.$request->id]],
        ]]);
    }

    /** "Yes, sign me in" or "It was not me" under the prompt. */
    public function answer(Callback $cb, bool $approve, int $requestId): void
    {
        $language = in_array($cb->languageCode, ['am', 'ti'], true) ? $cb->languageCode : 'en';
        $request = OwnerLoginRequest::query()->find($requestId);

        if ($cb->messageId && $cb->chatId) {
            $this->telegram->clearButtons($cb->chatId, $cb->messageId);
        }

        if (! $request || $request->status !== 'pending' || $request->isExpired() || (int) $request->telegram_user_id !== $cb->userId) {
            $this->telegram->answerCallback($cb->id, $this->messages->text('login_expired', $language), alert: true);

            return;
        }

        $member = Member::findByTelegramUser($cb->userId);
        $owner = BusinessOwner::query()->where('telegram_user_id', $cb->userId)->first();

        if (! $approve || $member?->isBanned() || $owner?->banned_at !== null) {
            $request->forceFill(['status' => 'declined'])->save();
            $this->telegram->answerCallback($cb->id);
            $this->telegram->sendText($cb->userId, $this->messages->text('login_declined', $language));

            return;
        }

        $name = trim(($cb->from['first_name'] ?? '').' '.($cb->from['last_name'] ?? ''));
        $owner = BusinessOwner::query()->updateOrCreate(['telegram_user_id' => $cb->userId], [
            'display_name' => mb_substr($member?->display_name ?: ($name !== '' ? $name : 'Business owner'), 0, 120),
            'telegram_username' => isset($cb->from['username']) ? mb_substr((string) $cb->from['username'], 0, 64) : null,
            'member_id' => $member?->id,
            'language' => $member?->language ?? $language,
        ]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $request->forceFill([
            'status' => 'approved',
            'owner_id' => $owner->id,
            'approved_at' => now(),
            'confirm_code_hash' => $this->codeHash($request->ref, $code),
            'attempts' => 0,
            // Ten minutes to type the code, from now.
            'expires_at' => now()->addMinutes(OwnerLoginRequest::LIFETIME_MINUTES),
        ])->save();

        $this->telegram->answerCallback($cb->id);
        $this->telegram->sendText($cb->userId, $this->messages->text('login_code', $language, ['code' => $code]));
    }

    /**
     * The website asks how it went: still waiting, the person said yes (now
     * ask for the code), or it is over.
     *
     * @return array{status: string}
     */
    public function poll(string $ref, string $secret): array
    {
        $request = $this->find($ref, $secret);

        if ($request === null) {
            return ['status' => 'unknown'];
        }

        if (in_array($request->status, ['pending', 'approved'], true) && $request->isExpired()) {
            return ['status' => 'expired'];
        }

        return ['status' => match ($request->status) {
            'pending' => 'pending',
            'approved' => 'confirmed',
            default => $request->status,
        }];
    }

    /**
     * The code from Telegram, typed on the website. Right once: a token.
     *
     * @return array{status: string, token?: string, owner?: array, attempts_left?: int}
     */
    public function verify(string $ref, string $secret, string $code): array
    {
        $request = $this->find($ref, $secret);

        if ($request === null) {
            return ['status' => 'unknown'];
        }
        if ($request->status !== 'approved') {
            return ['status' => $request->status === 'pending' ? 'pending' : 'expired'];
        }
        if ($request->isExpired()) {
            return ['status' => 'expired'];
        }

        // Count the try before judging it, in one statement: parallel guesses each use up
        // a try of their own instead of all reading the same count.
        $counted = OwnerLoginRequest::query()->whereKey($request->id)
            ->where('status', 'approved')->where('attempts', '<', self::MAX_ATTEMPTS)
            ->increment('attempts');
        if ($counted !== 1) {
            return ['status' => 'locked'];
        }

        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6 || ! hash_equals((string) $request->confirm_code_hash, $this->codeHash($request->ref, $code))) {
            $attempts = (int) OwnerLoginRequest::query()->whereKey($request->id)->value('attempts');
            if ($attempts >= self::MAX_ATTEMPTS) {
                OwnerLoginRequest::query()->whereKey($request->id)->where('status', 'approved')->update(['status' => 'declined', 'updated_at' => now()]);

                return ['status' => 'locked'];
            }

            return ['status' => 'wrong', 'attempts_left' => self::MAX_ATTEMPTS - $attempts];
        }

        // Exactly once, even if two submissions arrive together.
        $claimed = OwnerLoginRequest::query()->whereKey($request->id)->where('status', 'approved')->update(['status' => 'used', 'updated_at' => now()]);
        if ($claimed !== 1) {
            return ['status' => 'expired'];
        }

        $owner = $request->owner;
        $owner->forceFill(['last_login_at' => now()])->save();
        $token = $owner->createToken('site', ['owner'], now()->addDays(60))->plainTextToken;
        AuditLog::record(null, 'owner.login', 'business_owner', $owner->id, null, ['ip' => $request->ip]);

        return ['status' => 'approved', 'token' => $token, 'owner' => $owner->payload()];
    }

    private function find(string $ref, string $secret): ?OwnerLoginRequest
    {
        $request = OwnerLoginRequest::query()->where('ref', $ref)->first();

        return $request && hash_equals($request->secret_hash, OwnerLoginRequest::hashSecret($secret)) ? $request : null;
    }

    private function codeHash(string $ref, string $code): string
    {
        return hash_hmac('sha256', $ref.':'.$code, (string) config('app.key'));
    }

    /** "Chrome on Windows": enough for the person to recognise their own screen. */
    private function device(?string $agent): string
    {
        $agent = (string) $agent;
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => '',
        };
        $system = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS X') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim($browser.($browser !== '' && $system !== '' ? ' · ' : '').$system) ?: '?';
    }
}
