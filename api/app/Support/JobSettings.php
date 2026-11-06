<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Every operator value the jobs pipeline reads, in one place, edited on the
 * admin's Jobs settings page and stored in platform_settings.
 */
final class JobSettings
{
    public const DEFAULT_MODEL = 'claude-opus-5-5';

    public const ANTHROPIC_KEY = 'anthropic_api_key_encrypted';

    /** The channel jobs are published to: a numeric id (-100…) or @username. Empty = publish without posting. */
    public static function channel(): string
    {
        return RuntimeEnv::get(null, 'job_channel_id', null);
    }

    /** Whether a Telegram chat is the channel above, however the setting names it. */
    public static function isJobChannel(array $chat): bool
    {
        $channel = self::channel();

        if ($channel === '') {
            return false;
        }

        return (string) ($chat['id'] ?? '') === $channel
            || (isset($chat['username']) && strcasecmp('@'.$chat['username'], '@'.ltrim($channel, '@')) === 0);
    }

    /** At or above this scam score a job waits for a moderator. */
    public static function reviewThreshold(): int
    {
        return self::int('job_review_threshold', 50);
    }

    /** At or above this score the card carries a warning line. */
    public static function warningThreshold(): int
    {
        return self::int('job_warning_threshold', 30);
    }

    /** When off, every job goes to the review queue, whatever its score. */
    public static function autoPublish(): bool
    {
        return RuntimeEnv::get(null, 'job_auto_publish', null, '1') !== '0';
    }

    /** @return array<int, string> languages whose summary appears on the channel card */
    public static function cardLanguages(): array
    {
        $raw = RuntimeEnv::get(null, 'job_card_languages', null, 'am,ti');
        $langs = array_values(array_intersect(array_map('trim', explode(',', $raw)), ['am', 'ti', 'en', 'om']));

        return $langs === [] ? ['am'] : $langs;
    }

    /** @return array<int, string> lower-cased phrases moderators have marked as scam signals */
    public static function scamPhrases(): array
    {
        return self::lines('job_scam_phrases');
    }

    /** @return array<int, string> normalised company names whose listings always go to review */
    public static function blockedCompanies(): array
    {
        return array_values(array_unique(array_map([self::class, 'normaliseCompany'], self::lines('job_blocked_companies'))));
    }

    public static function blockCompany(string $company): void
    {
        $company = trim($company);

        if ($company === '' || in_array(self::normaliseCompany($company), self::blockedCompanies(), true)) {
            return;
        }

        $lines = self::lines('job_blocked_companies', lower: false);
        $lines[] = $company;
        PlatformSetting::put('job_blocked_companies', implode("\n", $lines));
    }

    public static function anthropicKey(): string
    {
        $stored = trim((string) PlatformSetting::valueFor(self::ANTHROPIC_KEY, ''));
        $key = $stored !== '' ? trim((string) SecretBox::open($stored)) : '';

        return $key !== '' ? $key : RuntimeEnv::get('services.anthropic.key', null, 'ANTHROPIC_API_KEY');
    }

    public static function setAnthropicKey(string $key): void
    {
        PlatformSetting::put(self::ANTHROPIC_KEY, SecretBox::seal(trim($key)));
    }

    /** A key was saved but can no longer be read. */
    public static function anthropicKeyIsUnreadable(): bool
    {
        $stored = trim((string) PlatformSetting::valueFor(self::ANTHROPIC_KEY, ''));

        return $stored !== '' && SecretBox::open($stored) === null;
    }

    public static function model(): string
    {
        return RuntimeEnv::get(null, 'anthropic_model', null, self::DEFAULT_MODEL);
    }

    public static function normaliseCompany(?string $company): string
    {
        $company = mb_strtolower(trim((string) $company));
        $company = preg_replace('/\b(llc|l\.l\.c|fze|fzco|fz-llc|ltd|limited|co|company|est|establishment|trading|group|inc)\b\.?/u', '', $company) ?? $company;

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $company) ?? '');
    }

    private static function int(string $key, int $default): int
    {
        $value = RuntimeEnv::get(null, $key, null);

        return is_numeric($value) ? max(0, min(100, (int) $value)) : $default;
    }

    /** @return array<int, string> */
    private static function lines(string $key, bool $lower = true): array
    {
        $raw = (string) PlatformSetting::valueFor($key, '');
        $lines = array_map('trim', preg_split('/\r?\n/', $raw) ?: []);
        $lines = array_values(array_filter($lines, fn (string $line) => $line !== ''));

        return $lower ? array_map('mb_strtolower', $lines) : $lines;
    }
}
