<?php

namespace App\Services;

use App\Bot\Incoming;
use App\Models\AuditLog;
use App\Models\Member;
use App\Support\Phone;
use Illuminate\Support\Facades\Log;

/**
 * Turns a shared contact card into a member record, and refuses what must
 * be refused: a card that is not the sender's own, a number that cannot be
 * read, and a number that already belongs to another Telegram account.
 */
class MemberRegistrar
{
    /**
     * @return array{ok: bool, reason: ?string, member: ?Member, notify: ?Member, outside: bool}
     */
    public function verifyContact(Incoming $in, string $language): array
    {
        $contact = $in->contact ?? [];

        // Telegram fills message.contact only from the request_contact button,
        // and contact.user_id proves the card is the sender's, not a forward.
        if ((int) ($contact['user_id'] ?? 0) !== $in->userId) {
            return $this->refusal('phone_not_own');
        }

        $digits = Phone::normalize((string) ($contact['phone_number'] ?? ''));

        if ($digits === null) {
            return $this->refusal('phone_unreadable');
        }

        $hash = Phone::hash($digits);
        $country = Phone::country($digits);
        $outside = ! in_array($country, Phone::allowedCountries(), true);

        $existing = Member::query()->where('phone_hash', $hash)->first();

        if ($existing && (int) $existing->telegram_user_id !== $in->userId) {
            Log::info('A second Telegram account presented an existing member\'s phone.', [
                'member_id' => $existing->id, 'telegram_user_id' => $in->userId,
            ]);

            return ['ok' => false, 'reason' => 'phone_taken', 'member' => null, 'notify' => $existing, 'outside' => $outside];
        }

        $member = Member::findByTelegramUser($in->userId);

        if ($member && $member->phone_hash !== $hash) {
            // The same Telegram account, a different number: keep the record,
            // move it to the new number. The old hash is free again.
            AuditLog::record(null, 'member.phone_changed', 'member', $member->id, ['phone_last4' => $member->phone_last4], ['phone_last4' => Phone::last4($digits)]);
        }

        $member ??= new Member;
        $member->forceFill([
            'telegram_user_id' => $in->userId,
            'chat_id' => $in->chatId,
            'telegram_username' => $in->username,
            'phone_hash' => $hash,
            'phone_last4' => Phone::last4($digits),
            'phone_country' => $country,
            'needs_review' => $outside,
            'display_name' => $member->display_name ?: $this->safeName($in->firstName),
            'language' => $language,
            'verified_at' => $member->verified_at ?? now(),
            'left_at' => null,
        ])->save();

        return ['ok' => true, 'reason' => null, 'member' => $member, 'notify' => null, 'outside' => $outside];
    }

    /** A display name: 2 to 40 characters, no links, no phone numbers, no @handles. */
    public function validName(string $name): bool
    {
        $name = trim($name);
        $length = mb_strlen($name);

        if ($length < 2 || $length > 40) {
            return false;
        }

        if (preg_match('/(https?:|www\.|t\.me|@|\d{6,})/iu', $name)) {
            return false;
        }

        return true;
    }

    public function safeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '' || ! $this->validName($name)) {
            return 'Member';
        }

        return mb_substr($name, 0, 40);
    }

    /** @return array{ok: bool, reason: string, member: null, notify: null, outside: bool} */
    private function refusal(string $reason): array
    {
        return ['ok' => false, 'reason' => $reason, 'member' => null, 'notify' => null, 'outside' => false];
    }
}
