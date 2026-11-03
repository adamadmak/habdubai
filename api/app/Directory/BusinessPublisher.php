<?php

namespace App\Directory;

use App\Bot\BotMessages;
use App\Bot\Choices;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Services\TelegramBotService;
use App\Support\Community;
use App\Support\JobSettings;
use App\Support\PublicUrls;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A moderator's decisions on listings, and what follows them: the website
 * shows approved listings straight from the database, the channel gets one
 * post when a listing first goes live, and the owner hears every decision
 * from the bot. A Telegram error never undoes a decision.
 */
class BusinessPublisher
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
        private readonly PhotoStore $photos,
    ) {}

    /** A new listing approved, or a hidden one put back. */
    public function approve(Business $business, int $adminId): void
    {
        $firstTime = in_array($business->status, ['pending', 'rejected'], true);

        DB::transaction(function () use ($business, $adminId, $firstTime): void {
            $business->forceFill([
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'review_note' => null,
            ])->save();
            // A first approval takes the submitted photo with it. Listing a hidden
            // business again does not: its owner's queued photo and edits wait for
            // their own decision on "Owner edits".
            if ($firstTime) {
                $this->photos->approvePending($business);
            }
        });

        if ($business->channel_message_id === null) {
            $this->post($business);
        }

        if ($firstTime) {
            $this->tellOwner($business, 'owner_listing_live', ['name' => e($business->name), 'link' => $this->listingLink($business)]);
        }
    }

    public function rejected(Business $business): void
    {
        $this->photos->discardPending($business);
        $this->tellOwner($business, 'owner_listing_rejected', ['name' => e($business->name), 'note' => e($business->review_note ?: '—')]);
    }

    /** Off the website; the channel post goes too. */
    public function unpost(Business $business): void
    {
        $channel = JobSettings::channel();
        if ($channel === '' || ! $business->channel_message_id) {
            return;
        }

        // Forgotten only once it is really gone; otherwise listing it again would post a duplicate
        // while the old post stays up with nothing pointing at it.
        if ($this->telegram->deleteMessage($channel, (int) $business->channel_message_id)) {
            $business->forceFill(['channel_message_id' => null])->save();
        } else {
            Log::warning('A directory post could not be removed from the channel; remove it by hand.', [
                'business_id' => $business->id, 'message_id' => $business->channel_message_id,
            ]);
        }
    }

    /** An owner's edits to a live listing: applied, the channel post refreshed, the owner told. */
    public function approveChanges(Business $business, int $adminId): void
    {
        $changes = array_intersect_key($business->pending_changes ?? [], array_flip(Business::OWNER_FIELDS));
        $before = $business->only(array_keys($changes));

        $newPhoto = DB::transaction(function () use ($business, $changes, $adminId): bool {
            $business->fill($changes);
            $business->forceFill(['pending_changes' => null, 'changes_submitted_at' => null, 'reviewed_by' => $adminId, 'reviewed_at' => now()])->save();

            return $this->photos->approvePending($business);
        });

        AuditLog::record($adminId, 'directory.changes_approved', 'business', $business->id, $before, $changes);
        $newPhoto ? $this->photoChanged($business) : $this->refresh($business);
        $this->tellOwner($business, 'owner_changes_live', ['name' => e($business->name), 'link' => $this->listingLink($business)]);
    }

    public function rejectChanges(Business $business, int $adminId, ?string $note): void
    {
        $business->forceFill(['pending_changes' => null, 'changes_submitted_at' => null])->save();
        $this->photos->discardPending($business);
        AuditLog::record($adminId, 'directory.changes_rejected', 'business', $business->id, null, ['note' => $note]);
        $this->tellOwner($business, 'owner_changes_rejected', ['name' => e($business->name), 'note' => e($note ?: '—')]);
    }

    public function claimDecided(BusinessClaim $claim): void
    {
        $owner = $claim->owner;
        $business = $claim->business;
        if (! $owner || ! $business) {
            return;
        }

        $key = $claim->status === 'approved' ? 'owner_claim_approved' : 'owner_claim_rejected';
        $this->tell($owner->chatId(), $owner->language, $key, [
            'name' => e($business->name),
            'note' => e($claim->review_note ?: '—'),
            'link' => PublicUrls::site($owner->language.'/business') ?: '',
        ]);
    }

    // ---- the channel -------------------------------------------------------------

    public function channelText(Business $business): string
    {
        $l = Community::labelLanguage();
        $business->loadMissing('city');

        $lines = [
            $this->messages->text('dir_channel_new', $l),
            '',
            '<b>'.e($business->name).'</b>'.($business->verified ? ' ✅' : ''),
            e(Choices::label(Choices::BUSINESS_CATEGORIES, $business->category, $l)).' · '.e($business->area).', '.e($business->city?->name($l) ?? ''),
        ];

        if ($business->description) {
            $lines[] = '';
            $lines[] = e($business->description);
        }

        $contact = array_filter([
            $business->phone ? '📞 '.e($business->phone) : null,
            $business->telegram ? '✈️ @'.e($business->telegram) : null,
        ]);
        if ($contact !== []) {
            $lines[] = '';
            $lines[] = implode('  ', $contact);
        }

        if (! empty($business->languages)) {
            $lines[] = '🗣 '.e(implode(', ', array_map(fn (string $code) => Choices::label(Choices::SPOKEN_LANGUAGES, $code, $l), $business->languages)));
        }

        return implode("\n", $lines);
    }

    private function keyboard(Business $business): array
    {
        $l = Community::labelLanguage();
        $row = [];

        $site = PublicUrls::site($l.'/directory/'.$business->slug);
        if ($site !== '') {
            $row[] = ['text' => $this->messages->button('btn_dir_open_site', $l), 'url' => $site];
        }
        $row[] = ['text' => $this->messages->button('btn_dir_map', $l), 'url' => $business->mapLink()];

        return ['inline_keyboard' => [$row]];
    }

    private function post(Business $business): void
    {
        $channel = JobSettings::channel();
        if ($channel === '') {
            return;
        }

        $text = $this->channelText($business);
        $cover = $business->cover();

        try {
            $message = null;
            // Telegram fetches the photo itself, so it must be on a public https address.
            if ($cover && PublicUrls::apiIsPublic() && mb_strlen($text) <= 1024) {
                try {
                    $message = $this->telegram->sendPhotoById($channel, $cover->url(), $text, $this->keyboard($business));
                } catch (\Throwable) {
                    $message = null;
                }
            }
            $message ??= $this->telegram->sendText($channel, $text, $this->keyboard($business));

            $business->forceFill(['channel_message_id' => $message['message_id'] ?? null])->save();
        } catch (\Throwable $e) {
            Log::warning('A directory listing could not be posted to the channel.', ['business_id' => $business->id, 'error' => $e->getMessage()]);
        }
    }

    /** The channel post rewritten to match the listing as it now stands. */
    public function refresh(Business $business): void
    {
        $channel = JobSettings::channel();
        if ($channel === '' || ! $business->channel_message_id) {
            return;
        }

        $text = $this->channelText($business);
        // The post is a photo with a caption or a plain message; one of the two edits applies.
        if (! $this->telegram->editText($channel, (int) $business->channel_message_id, $text, $this->keyboard($business))) {
            $this->telegram->editCaption($channel, (int) $business->channel_message_id, \App\Support\TelegramText::clip($text, 1024), $this->keyboard($business));
        }
    }

    /**
     * The listing's photo changed or went: the channel post follows. A new photo is put on the
     * post where Telegram allows it; otherwise (a post with no photo left to show, or one Telegram
     * will not edit) the post is replaced, or stays and is logged if it cannot be removed.
     */
    public function photoChanged(Business $business): void
    {
        $channel = JobSettings::channel();
        if ($channel === '' || ! $business->channel_message_id) {
            return;
        }

        $business->unsetRelation('photos');
        $cover = $business->cover();

        if ($cover && PublicUrls::apiIsPublic() && $this->telegram->editPhoto($channel, (int) $business->channel_message_id, $cover->url(), $this->channelText($business), $this->keyboard($business))) {
            return;
        }

        $this->unpost($business);
        if ($business->channel_message_id === null) {
            $this->post($business);
        }
    }

    // ---- the owner ------------------------------------------------------------------

    private function listingLink(Business $business): string
    {
        $language = $business->owner?->language ?? 'en';
        $url = PublicUrls::site($language.'/directory/'.$business->slug);

        return $url !== '' ? "\n".$url : '';
    }

    private function tellOwner(Business $business, string $key, array $vars): void
    {
        $owner = $business->owner;
        if ($owner === null) {
            return;
        }

        $this->tell($owner->chatId(), $owner->language, $key, $vars);
    }

    private function tell(int $chatId, string $language, string $key, array $vars): void
    {
        try {
            $this->telegram->sendText($chatId, $this->messages->text($key, $language, $vars));
        } catch (\Throwable) {
            // The owner sees the decision on the website either way.
        }
    }
}
