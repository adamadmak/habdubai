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
 *   website → start(): a ref for the bot link, a secret only the browser keeps, a 4-digit code
 *   bot     → prompt(): /start login_<ref> shows the code and asks "sign in?"
 *   bot     → answer(): the same Telegram user who opened the link says yes or no
 *   website → poll(): with the secret, gets an owner token exactly once
 *
 * The QR code on a computer screen carries only the ref, so someone who
 * photographs it cannot collect the token. The code lets the person check
 * the prompt in Telegram belongs to the page in front of them.
 */
class OwnerLogin
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
    ) {}

    /** @return array{ref: string, secret: string, code: string, link: ?string, expires_at: string} */
    public function start(?string $ip, ?string $userAgent): array
    {
        OwnerLoginRequest::query()->where('expires_at', '<', now()->subDay())->delete();

        $ref = Str::random(24);
        $secret = Str::random(48);
        $code = (string) random_int(1000, 9999);

        $request = OwnerLoginRequest::query()->create([
            'ref' => $ref,
            'secret_hash' => OwnerLoginRequest::hashSecret($secret),
            'check_code' => $code,
            'status' => 'pending',
            'ip' => $ip ? mb_substr($ip, 0, 45) : null,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 200) : null,
            'expires_at' => now()->addMinutes(OwnerLoginRequest::LIFETIME_MINUTES),
        ]);

        return [
            'ref' => $ref,
            'secret' => $secret,
            'code' => $code,
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
            'code' => $request->check_code,
            'site' => e($site !== '' ? (string) parse_url($site, PHP_URL_HOST) : $this->messages->text('login_site_fallback', $language)),
        ]), ['inline_keyboard' => [
            [['text' => $this->messages->button('btn_login_yes', $language), 'callback_data' => 'lg:ok:'.$request->id]],
            [['text' => $this->messages->button('btn_login_no', $language), 'callback_data' => 'lg:no:'.$request->id]],
        ]]);
    }

    /** "Yes, sign me in" or "No" under the prompt. */
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

        $request->forceFill(['status' => 'approved', 'owner_id' => $owner->id, 'approved_at' => now()])->save();

        $this->telegram->answerCallback($cb->id);
        $this->telegram->sendText($cb->userId, $this->messages->text('login_done', $language));
    }

    /**
     * The website asks how it went. An approved request hands out its token
     * once; asking again, or with the wrong secret, gets nothing.
     *
     * @return array{status: string, token?: string, owner?: array}
     */
    public function poll(string $ref, string $secret): array
    {
        $request = OwnerLoginRequest::query()->where('ref', $ref)->first();

        if (! $request || ! hash_equals($request->secret_hash, OwnerLoginRequest::hashSecret($secret))) {
            return ['status' => 'unknown'];
        }

        if ($request->status === 'pending') {
            return ['status' => $request->isExpired() ? 'expired' : 'pending'];
        }

        if ($request->status !== 'approved') {
            return ['status' => $request->status];
        }

        // Exactly once, even if two polls arrive together.
        $claimed = OwnerLoginRequest::query()->whereKey($request->id)->where('status', 'approved')->update(['status' => 'used', 'updated_at' => now()]);
        if ($claimed !== 1) {
            return ['status' => 'used'];
        }

        $owner = $request->owner;
        $owner->forceFill(['last_login_at' => now()])->save();
        $token = $owner->createToken('site', ['owner'], now()->addDays(60))->plainTextToken;
        AuditLog::record(null, 'owner.login', 'business_owner', $owner->id, null, ['ip' => $request->ip]);

        return ['status' => 'approved', 'token' => $token, 'owner' => $owner->payload()];
    }
}
