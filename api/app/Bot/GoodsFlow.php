<?php

namespace App\Bot;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\GoodsMatch;
use App\Models\GoodsPost;
use App\Models\Member;
use App\Models\Rating;
use App\Services\TelegramBotService;
use App\Support\Community;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The goods board: travellers with spare kilos, and members who need
 * something carried between the UAE and Ethiopia. Trust only: no money moves
 * through the platform and the platform carries nothing.
 *
 *   /travel or /send → direction → date → from → to → kilos → kinds → text → post
 *   post → mirrored in the goods group, matches shown, matching owners told
 *   "Interested" (needs your own post on the other side, so the traveller
 *   always sees what is in the parcel) → the owner accepts → both get a
 *   link to each other → both press "We agreed" → kilos come off the trip
 *   → the day after the flight both are asked whether it arrived → two
 *   yeses complete it and open a 1–5 rating; a "no" goes to a moderator.
 */
class GoodsFlow
{
    public const STATES = ['gd_direction', 'gd_date', 'gd_from', 'gd_to', 'gd_kilos', 'gd_categories', 'gd_text', 'gd_confirm'];

    /** Days between check-in reminders while an answer is missing or "not yet". */
    public const CHECKIN_EVERY_DAYS = 2;

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
    ) {}

    // ---- menu and browsing ---------------------------------------------------

    public function menu(Member $member): void
    {
        $l = $member->language;
        $rows = [];
        foreach (GoodsPost::KINDS as $kind) {
            $rows[] = array_map(fn (string $direction) => [
                'text' => $this->messages->button("btn_goods_browse_{$kind}_{$direction}", $l),
                'callback_data' => "g:b:{$kind}:{$direction}",
            ], GoodsPost::DIRECTIONS);
        }

        $this->telegram->sendText($member->chat_id, $this->messages->text('goods_menu', $l), ['inline_keyboard' => $rows]);
    }

    private function browse(Member $member, string $kind, string $direction): void
    {
        if (! in_array($kind, GoodsPost::KINDS, true) || ! in_array($direction, GoodsPost::DIRECTIONS, true)) {
            return;
        }

        $posts = GoodsPost::query()->live()->with('member')->where('kind', $kind)->where('direction', $direction)
            ->where('member_id', '!=', $member->id)->orderBy('date')->orderBy('id')->limit(30)->get()
            ->filter(fn (GoodsPost $p) => $p->member?->isActive())->take(6)->values();

        if ($posts->isEmpty()) {
            $this->say($member, 'goods_browse_empty');

            return;
        }

        $this->sendList($member, $this->messages->button("btn_goods_browse_{$kind}_{$direction}", $member->language), $posts);
    }

    /** Numbered cards, each with its own Interested button. */
    private function sendList(Member $member, string $title, Collection $posts): void
    {
        $l = $member->language;
        $lines = ['<b>'.e($title).'</b>'];
        $rows = [];

        foreach ($posts->values() as $i => $post) {
            $lines[] = '';
            $lines[] = ($i + 1).'. '.$this->card($post, $l, short: true);
            $rows[] = [['text' => ($i + 1).'. '.$this->messages->button('btn_goods_interested', $l), 'callback_data' => 'g:i:'.$post->id]];
        }

        $this->telegram->sendText($member->chat_id, implode("\n", $lines), ['inline_keyboard' => $rows]);
    }

    // ---- writing a post ----------------------------------------------------------

    /** Start /travel or /send. With $interestIn, the request to that post goes out as soon as this one is posted. */
    public function begin(Member $member, Conversation $conversation, string $kind, ?GoodsPost $interestIn = null): void
    {
        if (GoodsMatch::blocked($member)) {
            $this->say($member, 'goods_blocked');

            return;
        }

        if (GoodsPost::openOf($member, $kind)->count() >= GoodsPost::MAX_OPEN) {
            $this->say($member, 'goods_max_open', ['max' => GoodsPost::MAX_OPEN]);

            return;
        }

        $data = ['kind' => $kind, 'categories' => []];

        if ($interestIn !== null) {
            $conversation->forceFill(['state' => 'gd_date', 'data' => $data + ['interest' => $interestIn->id, 'direction' => $interestIn->direction]])->save();
            $this->askDate($member, $kind);

            return;
        }

        $conversation->forceFill(['state' => 'gd_direction', 'data' => $data])->save();
        $this->askDirection($member);
    }

    public function handle(Member $member, Conversation $conversation, Incoming $in): void
    {
        $l = $member->language;
        $text = trim((string) $in->text);
        $data = $conversation->data ?? [];
        $kind = (string) ($data['kind'] ?? 'traveller');
        $direction = (string) ($data['direction'] ?? 'to_et');

        switch ($conversation->state) {
            case 'gd_direction':
                $picked = Choices::keyFromLabel(Choices::GOODS_DIRECTIONS, $text);
                if ($picked === null) {
                    $this->askDirection($member);

                    return;
                }
                $conversation->moveTo('gd_date', ['direction' => $picked]);
                $this->askDate($member, $kind);

                return;

            case 'gd_date':
                $date = self::parseDate($text);
                if ($date === null) {
                    $this->telegram->sendText($member->chat_id, $this->messages->text('goods_date_invalid', $l), $this->cancelKeyboard($l));

                    return;
                }
                $conversation->moveTo('gd_from', ['date' => $date->toDateString()]);
                $this->askCity($member, $direction, from: true);

                return;

            case 'gd_from':
            case 'gd_to':
                $from = $conversation->state === 'gd_from';
                $city = Choices::keyFromLabel(Choices::goodsCities($direction, $from), $text);
                if ($city === null) {
                    $this->askCity($member, $direction, $from);

                    return;
                }
                if ($from) {
                    $conversation->moveTo('gd_to', ['from' => $city]);
                    $this->askCity($member, $direction, from: false);
                } else {
                    $conversation->moveTo('gd_kilos', ['to' => $city]);
                    $this->askKilos($member, $kind);
                }

                return;

            case 'gd_kilos':
                $kilos = preg_match('/\d+/', $text, $m) ? (int) $m[0] : 0;
                if ($kilos < 1 || $kilos > Choices::GOODS_MAX_KILOS) {
                    $this->telegram->sendText($member->chat_id, $this->messages->text('goods_kilos_invalid', $l, ['max' => Choices::GOODS_MAX_KILOS]), $this->kilosKeyboard($l, $kind));

                    return;
                }
                $conversation->moveTo('gd_categories', ['kilos' => $kilos, 'categories' => []]);
                $this->telegram->sendText($member->chat_id, $this->messages->text('goods_ask_categories_'.$kind, $l), $this->categoriesKeyboard($l));

                return;

            case 'gd_categories':
                $picked = (array) ($data['categories'] ?? []);
                if ($this->messages->pressed('btn_done', $text)) {
                    if ($picked === []) {
                        $this->telegram->sendText($member->chat_id, $this->messages->text('goods_categories_need_one', $l), $this->categoriesKeyboard($l));

                        return;
                    }
                    $conversation->moveTo('gd_text');
                    $this->askText($member, $kind);

                    return;
                }
                $category = Choices::keyFromLabel(Choices::GOODS_CATEGORIES, $text);
                if ($category === null) {
                    $this->telegram->sendText($member->chat_id, $this->messages->text('goods_ask_categories_'.$kind, $l), $this->categoriesKeyboard($l));

                    return;
                }
                $picked = in_array($category, $picked, true) ? array_values(array_diff($picked, [$category])) : [...$picked, $category];
                $conversation->remember('categories', $picked);
                $labels = array_map(fn (string $c) => Choices::label(Choices::GOODS_CATEGORIES, $c, $l), $picked);
                $this->telegram->sendText($member->chat_id, $picked === []
                    ? $this->messages->text('goods_ask_categories_'.$kind, $l)
                    : $this->messages->text('goods_categories_picked', $l, ['list' => e(implode(', ', $labels))]), $this->categoriesKeyboard($l));

                return;

            case 'gd_text':
                $body = null;
                if (! ($kind === 'traveller' && $this->messages->pressed('btn_skip', $text))) {
                    $length = mb_strlen($text);
                    if ($length < 5 || $length > 300 || str_starts_with($text, '/')) {
                        $this->askText($member, $kind, 'goods_text_invalid');

                        return;
                    }
                    if (GroupModerator::bannedItemIn($text)) {
                        $this->askText($member, $kind, 'goods_banned_item');

                        return;
                    }
                    $body = $text;
                }
                $conversation->moveTo('gd_confirm', ['text' => $body]);
                $this->preview($member, $conversation);

                return;

            case 'gd_confirm':
                if (! $this->messages->pressed('btn_goods_post', $text)) {
                    $this->preview($member, $conversation);

                    return;
                }
                $this->publish($member, $conversation);

                return;
        }
    }

    private function draft(Member $member, array $data): GoodsPost
    {
        $traveller = ($data['kind'] ?? '') === 'traveller';

        $post = new GoodsPost([
            'member_id' => $member->id,
            'kind' => $traveller ? 'traveller' : 'sender',
            'direction' => $data['direction'],
            'date' => $data['date'],
            'city_from' => $data['from'],
            'city_to' => $data['to'],
            'kilos' => (int) $data['kilos'],
            'kilos_left' => $traveller ? (int) $data['kilos'] : null,
            'categories' => array_values((array) $data['categories']),
            'description' => $data['text'] ?? null,
            // Set from the website; the bot's own steps leave the purpose "send" and the fee to agree.
            'mode' => $traveller ? null : (($data['mode'] ?? 'send') === 'request' ? 'request' : 'send'),
            'price' => isset($data['price']) ? (int) $data['price'] : null,
            'currency' => isset($data['price']) && (int) $data['price'] > 0 ? ($data['currency'] ?? 'AED') : null,
            'status' => 'open',
        ]);
        $post->setRelation('member', $member);

        return $post;
    }

    private function preview(Member $member, Conversation $conversation): void
    {
        $l = $member->language;
        $this->telegram->sendText($member->chat_id, $this->messages->text('goods_preview', $l, [
            'card' => $this->card($this->draft($member, $conversation->data ?? []), $l),
        ]), $this->telegram->replyKeyboard([[$this->messages->button('btn_goods_post', $l)], [$this->messages->button('btn_cancel', $l)]]));
    }

    private function publish(Member $member, Conversation $conversation): void
    {
        $l = $member->language;
        $data = $conversation->data ?? [];
        $conversation->reset();

        // Checked again: time has passed since /travel or /send.
        $refusal = $this->refusal($member, (string) $data['kind'], (string) ($data['date'] ?? ''));
        if ($refusal !== null) {
            $key = ['blocked' => 'goods_blocked', 'max_open' => 'goods_max_open', 'date' => 'goods_date_invalid'][$refusal];
            $this->say($member, $key, $refusal === 'max_open' ? ['max' => GoodsPost::MAX_OPEN] : [], $this->telegram->removeKeyboard());

            return;
        }

        $pending = isset($data['interest']) ? GoodsPost::query()->with('member')->find((int) $data['interest']) : null;
        ['post' => $post, 'in_group' => $inGroup, 'others' => $others] = $this->create($member, $data, $pending?->id);
        $this->say($member, 'goods_posted', ['group' => $inGroup ? $this->messages->text('goods_posted_group', $l) : ''], $this->telegram->removeKeyboard());

        if ($pending !== null) {
            $this->sayStatus($member, $this->followUp($member, $post, $pending), $pending);
        }

        if ($others->isNotEmpty()) {
            $this->sendList($member, $this->messages->text('goods_matches_title', $l), $others);
        } elseif ($pending === null) {
            $this->say($member, 'goods_no_matches');
        }
    }

    /**
     * Why this member cannot add a post of this kind now, or null: blocked after failed deals,
     * already at the most open posts, or a date in the past.
     */
    public function refusal(Member $member, string $kind, string $date): ?string
    {
        return match (true) {
            GoodsMatch::blocked($member) => 'blocked',
            GoodsPost::openOf($member, $kind)->count() >= GoodsPost::MAX_OPEN => 'max_open',
            $date < GoodsPost::today() => 'date',
            default => null,
        };
    }

    /**
     * A new post, from the bot or the website: saved, put on the goods group, and the members
     * whose open posts fit it are told (once each). The post a request is waiting on is left out:
     * that member hears through the request.
     *
     * @return array{post: GoodsPost, in_group: bool, others: Collection<int, GoodsPost>}
     */
    public function create(Member $member, array $data, ?int $skipPostId = null): array
    {
        $post = $this->draft($member, $data);
        $post->save();
        AuditLog::record(null, 'goods.posted', 'goods_post', $post->id, null, ['member_id' => $member->id, 'kind' => $post->kind, 'direction' => $post->direction]);

        $inGroup = $this->postToGroup($post);
        $others = $post->candidates(5)->reject(fn (GoodsPost $c) => $c->id === $skipPostId)->values();

        foreach ($others->unique('member_id') as $match) {
            $owner = $match->member;
            if (! $owner?->chat_id) {
                continue;
            }
            try {
                $this->telegram->sendText($owner->chat_id, $this->messages->text('goods_new_match', $owner->language, [
                    'card' => $this->card($post, $owner->language),
                ]), ['inline_keyboard' => [[['text' => $this->messages->button('btn_goods_interested', $owner->language), 'callback_data' => 'g:i:'.$post->id]]]]);
            } catch (\Throwable) {
                // A member who blocked the bot still sees it on the board.
            }
        }

        return ['post' => $post, 'in_group' => $inGroup, 'others' => $others];
    }

    /**
     * The request a post was made for ("interested" before the member had a post of their own):
     * sent now if the two still pair.
     *
     * @return string closed|mismatch, or what sendRequest() answers
     */
    public function followUp(Member $member, GoodsPost $post, GoodsPost $pending): string
    {
        if (! $pending->isOpen() || ! $pending->member?->isActive()) {
            return 'closed';
        }

        return $post->pairsWith($pending) ? $this->sendRequest($member, $pending, $post) : 'mismatch';
    }

    // ---- interest and introductions ----------------------------------------------

    /** "Interested" on a post: from a list, a new-match notice, or the deep link under the group card. */
    public function interest(Member $member, int $postId): void
    {
        $post = GoodsPost::query()->with('member')->find($postId);
        $status = $this->ask($member, $post);

        if ($status === 'need_post') {
            $kind = $post->isTraveller() ? 'sender' : 'traveller';
            $this->say($member, 'goods_need_post_'.$kind);
            $this->begin($member, Conversation::forUser((int) $member->telegram_user_id, (int) $member->chat_id), $kind, $post);

            return;
        }

        $this->sayStatus($member, $status, $post);
    }

    /**
     * "Interested", from the bot or the website: the request goes to the post's owner, or the
     * answer says why not. Interest needs the member's own open post on the other side.
     *
     * @return string sent|accepted|already|need_post|closed|own|blocked|limit|unreachable
     */
    public function ask(Member $member, ?GoodsPost $post): string
    {
        if (! $post || ! $post->isOpen() || ! $post->member?->isActive()) {
            return 'closed';
        }
        if ($post->member_id === $member->id) {
            return 'own';
        }
        if (GoodsMatch::blocked($member)) {
            return 'blocked';
        }

        // They already asked about one of mine with this post: interest both ways is an acceptance.
        $reverse = GoodsMatch::query()->where('requester_post_id', $post->id)->where('owner_id', $member->id)->where('status', 'requested')->first();
        if ($reverse !== null) {
            $this->accept($member, $reverse);

            return 'accepted';
        }

        $known = GoodsMatch::query()->where(function ($q) use ($post, $member): void {
            $q->where(fn ($a) => $a->where('post_id', $post->id)->where('requester_id', $member->id))
                ->orWhere(fn ($b) => $b->where('requester_post_id', $post->id)->where('owner_id', $member->id)->whereIn('status', ['introduced', 'agreed', 'disputed']));
        })->exists();
        if ($known) {
            return 'already';
        }

        $mine = $post->candidates(1, $member->id)->first();

        return $mine === null ? 'need_post' : $this->sendRequest($member, $post, $mine);
    }

    /**
     * The request itself: the owner reads the asker's post and badge, nothing more, and answers
     * in Telegram.
     *
     * @return string sent|limit|already|unreachable
     */
    private function sendRequest(Member $requester, GoodsPost $post, GoodsPost $mine): string
    {
        $today = GoodsMatch::query()->where('requester_id', $requester->id)->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= GoodsMatch::REQUESTS_PER_DAY) {
            return 'limit';
        }

        $sender = $post->isTraveller() ? $mine : $post;

        try {
            $match = GoodsMatch::query()->create([
                'post_id' => $post->id,
                'requester_post_id' => $mine->id,
                'owner_id' => $post->member_id,
                'requester_id' => $requester->id,
                'status' => 'requested',
                'kilos' => $sender->kilos,
            ]);
        } catch (UniqueConstraintViolationException) {
            return 'already';
        }

        $owner = $post->member;
        $ol = $owner->language;
        $mine->setRelation('member', $requester);

        try {
            $this->telegram->sendText($owner->chat_id, $this->messages->text('goods_request', $ol, [
                'name' => e($requester->display_name),
                'card' => $this->card($mine, $ol),
            ]), ['inline_keyboard' => [[
                ['text' => $this->messages->button('btn_goods_accept', $ol), 'callback_data' => 'g:a:'.$match->id],
                ['text' => $this->messages->button('btn_goods_decline', $ol), 'callback_data' => 'g:d:'.$match->id],
            ]]]);
        } catch (\Throwable) {
            $match->forceFill(['status' => 'cancelled', 'closed_at' => now()])->save();

            return 'unreachable';
        }

        return 'sent';
    }

    /** The bot's words for what ask() or followUp() answered. "Accepted" has spoken already. */
    private function sayStatus(Member $member, string $status, GoodsPost $post): void
    {
        $name = ['name' => e((string) $post->member?->display_name)];

        match ($status) {
            'sent' => $this->say($member, 'goods_request_sent', $name),
            'unreachable' => $this->say($member, 'goods_request_unreachable', $name),
            'limit' => $this->say($member, 'goods_requests_limit', ['count' => GoodsMatch::REQUESTS_PER_DAY]),
            'already' => $this->say($member, 'goods_already_asked'),
            'own' => $this->say($member, 'goods_own_post'),
            'blocked' => $this->say($member, 'goods_blocked'),
            'mismatch' => $this->say($member, 'goods_interest_mismatch'),
            'closed' => $this->say($member, 'goods_post_closed'),
            default => null,
        };
    }

    private function accept(Member $owner, GoodsMatch $match): void
    {
        $match->load(['post.member', 'requesterPost.member', 'owner', 'requester']);

        if ($match->status !== 'requested' || $match->owner_id !== $owner->id) {
            $this->say($owner, 'goods_match_closed');

            return;
        }

        if (! $match->post?->isOpen() || ! $match->requesterPost?->isOpen() || ! $match->requester?->isActive()) {
            $match->forceFill(['status' => 'cancelled', 'closed_at' => now()])->save();
            $this->say($owner, 'goods_post_closed');

            return;
        }

        $match->forceFill(['status' => 'introduced', 'introduced_at' => now()])->save();
        AuditLog::record(null, 'goods.introduced', 'goods_match', $match->id);

        $this->say($owner, 'goods_request_accepted', ['name' => e($match->requester->display_name)]);

        foreach ([$match->owner, $match->requester] as $side) {
            $this->introduce($match, $side);
        }
    }

    private function introduce(GoodsMatch $match, Member $member): void
    {
        $l = $member->language;
        $other = $match->other($member);

        try {
            $this->telegram->sendText($member->chat_id, $this->messages->text('goods_introduced', $l, [
                'name' => e($other->display_name),
                'badge' => $this->badge($other, $l),
                'contact' => $this->messages->text('goods_contact', $l, ['link' => $this->mention($other)]),
            ]), $this->dealKeyboard($match, $l));
        } catch (\Throwable) {
            // The other side has the link either way.
        }
    }

    private function decline(Member $owner, GoodsMatch $match): void
    {
        if ($match->status !== 'requested' || $match->owner_id !== $owner->id) {
            $this->say($owner, 'goods_match_closed');

            return;
        }

        $match->forceFill(['status' => 'declined', 'closed_at' => now()])->save();
        $this->say($owner, 'goods_declined_owner');
        $this->tell($match->requester, 'goods_declined', ['name' => e($owner->display_name)]);
    }

    // ---- agreeing and dropping -------------------------------------------------------

    private function agree(Member $member, GoodsMatch $match): void
    {
        if ($match->status !== 'introduced' || ! $match->involves($member)) {
            $this->say($member, 'goods_match_closed');

            return;
        }

        // Both may press at the same moment: each records its own press on the locked row
        // and then reads the other's, so one of the two always sees both and finalises.
        $column = $match->isOwner($member) ? 'agreed_owner_at' : 'agreed_requester_at';
        $open = DB::transaction(function () use ($match, $column): bool {
            $fresh = GoodsMatch::query()->lockForUpdate()->find($match->id);
            if (! $fresh || $fresh->status !== 'introduced') {
                return false;
            }
            if ($fresh->{$column} === null) {
                $fresh->forceFill([$column => now()])->save();
            }
            $match->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });

        if (! $open) {
            $this->say($member, 'goods_match_closed');

            return;
        }

        if ($match->agreed_owner_at !== null && $match->agreed_requester_at !== null) {
            $this->finalise($match);

            return;
        }

        $other = $match->other($member);
        $this->say($member, 'goods_agree_waiting', ['name' => e($other->display_name)]);

        try {
            $this->telegram->sendText($other->chat_id, $this->messages->text('goods_agree_nudge', $other->language, ['name' => e($member->display_name)]),
                $this->dealKeyboard($match, $other->language));
        } catch (\Throwable) {
        }
    }

    /** Both pressed "We agreed": take the kilos off the trip and close the parcel, in one transaction. */
    private function finalise(GoodsMatch $match): void
    {
        $outcome = DB::transaction(function () use ($match): string {
            $fresh = GoodsMatch::query()->lockForUpdate()->findOrFail($match->id);
            if ($fresh->status !== 'introduced') {
                return 'closed';
            }

            $posts = GoodsPost::query()->lockForUpdate()->whereIn('id', [$fresh->post_id, $fresh->requester_post_id])->get();
            $trip = $posts->first(fn (GoodsPost $p) => $p->isTraveller());
            $parcel = $posts->first(fn (GoodsPost $p) => ! $p->isTraveller());

            if (! $trip || ! $parcel || $parcel->status !== 'open') {
                $fresh->forceFill(['status' => 'cancelled', 'closed_at' => now()])->save();

                return 'closed';
            }

            $kilos = (int) ($fresh->kilos ?? $parcel->kilos);
            if ($trip->room() < $kilos) {
                $fresh->forceFill(['status' => 'cancelled', 'closed_at' => now()])->save();

                return 'no_room';
            }

            $left = $trip->room() - $kilos;
            $trip->forceFill(['kilos_left' => $left, 'status' => $left < 1 ? 'matched' : $trip->status])->save();
            $parcel->forceFill(['status' => 'matched'])->save();
            $fresh->forceFill([
                'status' => 'agreed',
                'agreed_at' => now(),
                'kilos' => $kilos,
                'checkin_on' => $trip->date->copy()->addDay()->toDateString(),
            ])->save();

            // The parcel is spoken for: other requests about it end here.
            GoodsMatch::query()->where('id', '!=', $fresh->id)->whereIn('status', ['requested', 'introduced'])
                ->where(fn ($q) => $q->where('post_id', $parcel->id)->orWhere('requester_post_id', $parcel->id))
                ->update(['status' => 'cancelled', 'closed_at' => now(), 'updated_at' => now()]);

            return 'agreed';
        });

        $match->refresh()->load(['post.member', 'requesterPost.member', 'owner', 'requester']);

        if ($outcome !== 'agreed') {
            foreach ([$match->owner, $match->requester] as $side) {
                $this->tell($side, $outcome === 'no_room' ? 'goods_no_room' : 'goods_post_closed');
            }

            return;
        }

        AuditLog::record(null, 'goods.agreed', 'goods_match', $match->id, null, ['kilos' => $match->kilos]);

        $trip = $match->travellerPost();
        foreach ([$match->owner, $match->requester] as $side) {
            $this->tell($side, 'goods_agreed', [
                'name' => e($match->other($side)->display_name),
                'kilos' => $match->kilos,
                'route' => e($this->route($trip, $side->language)),
                'date' => $trip->date->format('j M Y'),
                'checkin' => $match->checkin_on->format('j M Y'),
            ]);
        }

        $this->refreshGroupCard($match->post);
        $this->refreshGroupCard($match->requesterPost);
    }

    /** "Not going ahead": a request withdrawn, or an introduction or agreement called off before the check-in. */
    private function drop(Member $member, GoodsMatch $match): void
    {
        $was = $match->status;
        $allowed = $match->involves($member) && (
            ($was === 'requested' && ! $match->isOwner($member))
            || $was === 'introduced'
            || ($was === 'agreed' && $match->checkin_sent_at === null)
        );

        if (! $allowed) {
            $this->say($member, 'goods_match_closed');

            return;
        }

        // Re-checked on the locked row: if both press "Not going ahead" together, only the
        // first gives the kilos back.
        $dropped = DB::transaction(function () use ($match, $was): bool {
            $fresh = GoodsMatch::query()->lockForUpdate()->find($match->id);
            if (! $fresh || $fresh->status !== $was) {
                return false;
            }
            $fresh->forceFill(['status' => 'cancelled', 'closed_at' => now()])->save();
            $match->setRawAttributes($fresh->getAttributes(), true);

            if ($was !== 'agreed') {
                return true;
            }

            // Give the kilos back, and reopen what the agreement closed if its time has not passed.
            foreach (GoodsPost::query()->lockForUpdate()->whereIn('id', [$match->post_id, $match->requester_post_id])->get() as $post) {
                if ($post->isTraveller()) {
                    $post->kilos_left = min($post->kilos, $post->room() + (int) $match->kilos);
                }
                if ($post->status === 'matched' && $post->lastDay()->toDateString() >= GoodsPost::today()) {
                    $post->status = 'open';
                }
                $post->save();
            }

            return true;
        });

        if (! $dropped) {
            $this->say($member, 'goods_match_closed');

            return;
        }

        AuditLog::record(null, 'goods.dropped', 'goods_match', $match->id, ['status' => $was], ['by' => $member->id]);
        $this->say($member, 'goods_dropped');

        if ($was !== 'requested') {
            $other = $match->other($member);
            $trip = $match->travellerPost();
            $this->tell($other, 'goods_dropped_other', [
                'name' => e($member->display_name),
                'route' => e($this->route($trip, $other->language)),
                'date' => $trip->date->format('j M Y'),
            ]);
        }

        if ($was === 'agreed') {
            $this->refreshGroupCard($match->post->fresh());
            $this->refreshGroupCard($match->requesterPost->fresh());
        }
    }

    // ---- check-in, completion, rating, dispute ------------------------------------------

    private function checkin(Member $member, GoodsMatch $match, string $answer): void
    {
        if (! in_array($answer, ['yes', 'no', 'wait'], true)) {
            return;
        }

        if ($match->status !== 'agreed' || ! $match->involves($member)) {
            $this->say($member, 'goods_match_closed');

            return;
        }

        // Recorded on the locked row and read back, so two answers at the same moment
        // still see each other.
        $column = $match->isOwner($member) ? 'checkin_owner' : 'checkin_requester';
        $open = DB::transaction(function () use ($match, $column, $answer): bool {
            $fresh = GoodsMatch::query()->lockForUpdate()->find($match->id);
            if (! $fresh || $fresh->status !== 'agreed') {
                return false;
            }
            $fresh->forceFill([$column => $answer])->save();
            $match->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });

        if (! $open) {
            $this->say($member, 'goods_match_closed');

            return;
        }

        if ($answer === 'no') {
            $this->dispute($match);

            return;
        }

        if ($match->checkin_owner === 'yes' && $match->checkin_requester === 'yes') {
            $this->complete($match);

            return;
        }

        $this->say($member, $answer === 'wait' ? 'goods_checkin_wait' : 'goods_checkin_thanks');
    }

    /** Delivered: both get a deal on their record and a 1–5 rating to give. */
    public function complete(GoodsMatch $match, ?int $actorUserId = null, ?string $note = null): void
    {
        $was = $match->status;

        DB::transaction(function () use ($match, $actorUserId, $note): void {
            $match->forceFill(['status' => 'completed', 'closed_at' => now(), 'moderator_note' => $note ?? $match->moderator_note, 'resolved_by' => $actorUserId])->save();
            Member::query()->whereKey([$match->owner_id, $match->requester_id])->increment('trips_completed');
        });

        AuditLog::record($actorUserId, 'goods.completed', 'goods_match', $match->id, ['status' => $was]);

        $match->load(['owner', 'requester']);
        foreach ([$match->owner, $match->requester] as $side) {
            $stars = array_map(fn (int $n) => ['text' => $n.' ⭐', 'callback_data' => "g:r:{$match->id}:{$n}"], range(1, 5));
            try {
                $this->telegram->sendText($side->chat_id, $this->messages->text('goods_completed', $side->language, [
                    'name' => e($match->other($side)->display_name),
                ]), ['inline_keyboard' => [$stars]]);
            } catch (\Throwable) {
            }
        }
    }

    private function rate(Member $member, GoodsMatch $match, int $stars): void
    {
        if ($stars < 1 || $stars > 5) {
            return;
        }

        if ($match->status !== 'completed' || ! $match->involves($member)) {
            $this->say($member, 'goods_match_closed');

            return;
        }

        try {
            Rating::query()->create([
                'match_id' => $match->id,
                'from_member_id' => $member->id,
                'to_member_id' => $match->other($member)->id,
                'stars' => $stars,
                'visible_at' => now()->addDays(Rating::VISIBLE_AFTER_DAYS),
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->say($member, 'goods_already_rated');

            return;
        }

        // Both sides have rated: show both now.
        if (Rating::query()->where('match_id', $match->id)->count() >= 2) {
            Rating::query()->where('match_id', $match->id)->update(['visible_at' => now()]);
            Rating::recount($match->owner_id);
            Rating::recount($match->requester_id);
        }

        $this->say($member, 'goods_rated');
    }

    private function dispute(GoodsMatch $match): void
    {
        $match->forceFill(['status' => 'disputed'])->save();
        $match->load(['post.member', 'requesterPost.member', 'owner', 'requester']);
        AuditLog::record(null, 'goods.disputed', 'goods_match', $match->id);

        foreach ([$match->owner, $match->requester] as $side) {
            $this->tell($side, 'goods_disputed');
        }

        $trip = $match->travellerPost();
        $parcel = $match->senderPost();
        $moderators = Member::query()->whereIn('role', ['moderator', 'admin'])->whereNotNull('chat_id')->get()->filter(fn (Member $m) => $m->isActive());

        foreach ($moderators as $moderator) {
            try {
                $this->telegram->notify($moderator->chat_id, $this->messages->text('goods_dispute_alert', $moderator->language, [
                    'id' => $match->id,
                    'route' => e($this->route($trip, $moderator->language)),
                    'date' => $trip->date->format('j M Y'),
                    'traveller' => e($trip->member?->display_name ?? '?'),
                    'sender' => e($parcel?->member?->display_name ?? '?'),
                ]));
            } catch (\Throwable) {
            }
        }
    }

    // ---- moderator decisions (admin page) ---------------------------------------------------

    /** A moderator closes a match: delivered, failed (with the member at fault), or cancelled. */
    public function resolve(GoodsMatch $match, string $outcome, ?int $failedMemberId, ?string $note, int $actorUserId): void
    {
        $match->load(['post.member', 'requesterPost.member', 'owner', 'requester']);
        $was = $match->status;

        if ($outcome === 'completed') {
            $this->complete($match, $actorUserId, $note);
        } else {
            $match->forceFill([
                'status' => $outcome,
                'closed_at' => now(),
                'failed_member_id' => $outcome === 'failed' ? $failedMemberId : null,
                'moderator_note' => $note,
                'resolved_by' => $actorUserId,
            ])->save();
            AuditLog::record($actorUserId, 'goods.resolved', 'goods_match', $match->id, ['status' => $was], ['status' => $outcome, 'failed_member_id' => $match->failed_member_id]);
        }

        $trip = $match->travellerPost();
        foreach ([$match->owner, $match->requester] as $side) {
            $this->tell($side, 'goods_resolved', [
                'route' => e($this->route($trip, $side->language)),
                'date' => $trip->date->format('j M Y'),
                'outcome' => $this->messages->text('goods_outcome_'.$outcome, $side->language),
            ]);
        }

        $atFault = $outcome === 'failed' && $failedMemberId ? Member::query()->find($failedMemberId) : null;
        if ($atFault && GoodsMatch::blocked($atFault)) {
            foreach (GoodsPost::query()->where('member_id', $atFault->id)->where('status', 'open')->get() as $post) {
                $this->hide($post, 'Two failed deals within a year.', $actorUserId, notify: false);
            }
            $this->tell($atFault, 'goods_posts_hidden');
        }
    }

    /** Take a post off the board; requests about it end, and the group card is deleted. */
    public function hide(GoodsPost $post, string $reason, ?int $actorUserId, bool $notify = true): void
    {
        $was = $post->status;
        $post->forceFill(['status' => 'hidden', 'hidden_reason' => mb_substr($reason, 0, 250)])->save();

        GoodsMatch::query()->whereIn('status', ['requested', 'introduced'])
            ->where(fn ($q) => $q->where('post_id', $post->id)->orWhere('requester_post_id', $post->id))
            ->update(['status' => 'cancelled', 'closed_at' => now(), 'updated_at' => now()]);

        $group = Community::goodsGroup();
        if ($group !== '' && $post->group_message_id) {
            $this->telegram->deleteMessage($group, (int) $post->group_message_id);
        }

        AuditLog::record($actorUserId, 'goods.hidden', 'goods_post', $post->id, ['status' => $was], ['reason' => $reason]);

        if ($notify && $post->member) {
            $this->tell($post->member, 'goods_post_removed', [
                'route' => e($this->route($post, $post->member->language)),
                'date' => $post->date->format('j M Y'),
                'reason' => e($reason),
            ]);
        }
    }

    // ---- my posts ------------------------------------------------------------------------------

    public function mine(Member $member): void
    {
        $l = $member->language;
        $posts = GoodsPost::openOf($member)->orderBy('date')->get();
        $matches = GoodsMatch::query()->with(['post', 'requesterPost', 'owner', 'requester'])
            ->whereIn('status', ['requested', 'introduced', 'agreed', 'disputed'])
            ->where(fn ($q) => $q->where('owner_id', $member->id)->orWhere('requester_id', $member->id))
            ->latest('id')->limit(10)->get();

        if ($posts->isEmpty() && $matches->isEmpty()) {
            $this->say($member, 'goods_mine_none');

            return;
        }

        $lines = [$this->messages->text('goods_mine_title', $l)];
        $rows = [];

        if ($posts->isNotEmpty()) {
            $lines[] = '';
            $lines[] = $this->messages->text('goods_mine_posts', $l);
            foreach ($posts->values() as $i => $post) {
                $lines[] = ($i + 1).'. '.($post->isTraveller() ? '🧳 ' : '📦 ').e($this->route($post, $l)).' · '.$post->date->format('j M')
                    .' · '.$this->messages->button('btn_goods_kilos', $l, ['kilos' => $post->isTraveller() ? $post->room() : $post->kilos]);
                $rows[] = [['text' => $this->messages->button('btn_goods_close', $l, ['n' => $i + 1]), 'callback_data' => 'g:z:'.$post->id]];
            }
        }

        if ($matches->isNotEmpty()) {
            $lines[] = '';
            $lines[] = $this->messages->text('goods_mine_deals', $l);
            foreach ($matches as $match) {
                $trip = $match->travellerPost();
                $lines[] = '• '.e($this->route($trip, $l)).' · '.$trip->date->format('j M').' · '.e($match->other($member)->display_name)
                    .': '.$this->messages->text('goods_state_'.$match->status, $l);
            }
        }

        $this->telegram->sendText($member->chat_id, implode("\n", $lines), $rows === [] ? null : ['inline_keyboard' => $rows]);
    }

    private function close(Member $member, int $postId): void
    {
        $this->say($member, $this->closePost($member, GoodsPost::query()->find($postId)) ? 'goods_closed' : 'goods_post_closed');
    }

    /** A member closes their own open post, from the bot or the website; requests still waiting on it are cancelled. */
    public function closePost(Member $member, ?GoodsPost $post): bool
    {
        if (! $post || $post->member_id !== $member->id || $post->status !== 'open') {
            return false;
        }

        $post->forceFill(['status' => 'closed'])->save();
        GoodsMatch::query()->whereIn('status', ['requested', 'introduced'])
            ->where(fn ($q) => $q->where('post_id', $post->id)->orWhere('requester_post_id', $post->id))
            ->update(['status' => 'cancelled', 'closed_at' => now(), 'updated_at' => now()]);

        AuditLog::record(null, 'goods.closed', 'goods_post', $post->id, null, ['by' => $member->id]);
        $this->refreshGroupCard($post);

        return true;
    }

    // ---- buttons ----------------------------------------------------------------------------------

    /** Every g:… button. Interest also works under the group card; the rest only in a private chat. */
    public function button(Member $member, Callback $cb): void
    {
        $parts = $cb->parts();
        $action = $parts[1] ?? '';
        $id = (int) ($parts[2] ?? 0);

        if ($action === 'i') {
            $this->interest($member, $id);
            $this->telegram->answerCallback($cb->id, $cb->inPrivateChat() ? null : $this->messages->text('q_check_dm', $member->language), alert: ! $cb->inPrivateChat());

            return;
        }

        if (! $cb->inPrivateChat()) {
            $this->telegram->answerCallback($cb->id);

            return;
        }

        if ($action === 'b') {
            $this->browse($member, $parts[2] ?? '', $parts[3] ?? '');
            $this->telegram->answerCallback($cb->id);

            return;
        }

        if ($action === 'z') {
            $this->close($member, $id);
            $this->telegram->answerCallback($cb->id);

            return;
        }

        $match = GoodsMatch::query()->with(['post.member', 'requesterPost.member', 'owner', 'requester'])->find($id);

        if ($match === null || ! $match->involves($member)) {
            $this->telegram->answerCallback($cb->id);

            return;
        }

        // A button that settles something is used once; take it off the message.
        if (in_array($action, ['a', 'd', 'c', 'r'], true) && $cb->messageId) {
            $this->telegram->clearButtons($member->chat_id, $cb->messageId);
        }

        match ($action) {
            'a' => $this->accept($member, $match),
            'd' => $this->decline($member, $match),
            'ok' => $this->agree($member, $match),
            'x' => $this->drop($member, $match),
            'c' => $this->checkin($member, $match, (string) ($parts[3] ?? '')),
            'r' => $this->rate($member, $match, (int) ($parts[3] ?? 0)),
            default => null,
        };

        $this->telegram->answerCallback($cb->id);
    }

    // ---- scheduled chores ------------------------------------------------------------------------------

    /** Close posts past their last day, and requests whose trip has flown. */
    public function expire(): int
    {
        $posts = GoodsPost::query()->stale()->get();

        foreach ($posts as $post) {
            $post->forceFill(['status' => 'expired'])->save();
            $this->refreshGroupCard($post);
        }

        $today = GoodsPost::today();
        $matches = GoodsMatch::query()->with(['post', 'requesterPost'])->whereIn('status', ['requested', 'introduced'])->get();
        foreach ($matches as $match) {
            $trip = $match->travellerPost();
            if ($trip === null || $trip->date->toDateString() < $today) {
                $match->forceFill(['status' => 'expired', 'closed_at' => now()])->save();
            }
        }

        return $posts->count();
    }

    /** Ask both sides whether the parcel arrived, from the day after the flight; settle matches nobody answers. */
    public function sendCheckins(): int
    {
        $today = CarbonImmutable::parse(GoodsPost::today());
        $sent = 0;

        $due = GoodsMatch::query()->with(['post.member', 'requesterPost.member', 'owner', 'requester'])
            ->where('status', 'agreed')
            ->whereDate('checkin_on', '<=', $today->toDateString())
            ->where(fn ($q) => $q->whereNull('checkin_sent_at')->orWhere('checkin_sent_at', '<=', now()->subDays(self::CHECKIN_EVERY_DAYS)->addMinutes(30)))
            ->get();

        foreach ($due as $match) {
            if ($match->checkin_on->toDateString() <= $today->subDays(GoodsMatch::CHECKIN_DAYS)->toDateString()) {
                $this->settle($match);

                continue;
            }

            $trip = $match->travellerPost();
            foreach (['checkin_owner' => $match->owner, 'checkin_requester' => $match->requester] as $column => $side) {
                if (! in_array($match->{$column}, [null, 'wait'], true) || ! $side?->chat_id) {
                    continue;
                }
                $l = $side->language;
                try {
                    $this->telegram->sendText($side->chat_id, $this->messages->text('goods_checkin', $l, [
                        'name' => e($match->other($side)->display_name),
                        'route' => e($this->route($trip, $l)),
                        'date' => $trip->date->format('j M Y'),
                    ]), ['inline_keyboard' => [
                        [['text' => $this->messages->button('btn_goods_ci_yes', $l), 'callback_data' => "g:c:{$match->id}:yes"]],
                        [['text' => $this->messages->button('btn_goods_ci_no', $l), 'callback_data' => "g:c:{$match->id}:no"]],
                        [['text' => $this->messages->button('btn_goods_ci_wait', $l), 'callback_data' => "g:c:{$match->id}:wait"]],
                    ]]);
                    $sent++;
                } catch (\Throwable) {
                }
            }

            $match->forceFill(['checkin_sent_at' => now()])->save();
        }

        return $sent;
    }

    /** Two weeks after the check-in date: one yes and no complaint counts as delivered; silence counts as nothing. */
    private function settle(GoodsMatch $match): void
    {
        if (in_array('yes', [$match->checkin_owner, $match->checkin_requester], true)) {
            $this->complete($match);

            return;
        }

        $match->forceFill(['status' => 'unconfirmed', 'closed_at' => now()])->save();
        AuditLog::record(null, 'goods.unconfirmed', 'goods_match', $match->id);
    }

    // ---- cards ---------------------------------------------------------------------------------------------

    public function card(GoodsPost $post, string $l, bool $short = false): string
    {
        $categories = array_map(fn (string $c) => Choices::label(Choices::GOODS_CATEGORIES, $c, $l), (array) $post->categories);
        $notes = $post->description ? "\n📝 ".e($short ? Str::limit($post->description, 120) : $post->description) : '';
        $notes = ($post->mode === 'request' ? "\n".$this->messages->text('goods_mode_request', $l) : '').$notes.$this->feeLine($post, $l);

        return $this->messages->text($post->isTraveller() ? 'goods_card_traveller' : 'goods_card_sender', $l, [
            'route' => e($this->route($post, $l)),
            'date' => $post->date->format('j M Y'),
            'kilos' => $post->isTraveller() ? $post->room() : $post->kilos,
            'categories' => e(implode(', ', $categories)),
            'notes' => $notes,
            'badge' => $this->badge($post->member, $l),
        ]);
    }

    /** The carry fee, when one was set: per kilo for a trip, an offer for a parcel, or free. */
    private function feeLine(GoodsPost $post, string $l): string
    {
        if ($post->price === null) {
            return '';
        }
        if ((int) $post->price === 0) {
            return "\n".$this->messages->text('goods_fee_free', $l);
        }

        return "\n".$this->messages->text($post->isTraveller() ? 'goods_fee_per_kg' : 'goods_fee_total', $l, [
            'amount' => number_format((int) $post->price),
            'currency' => (string) $post->currency,
        ]);
    }

    /** Name, verified month, stars once rated, and completed deals: what lets members trust each other. */
    public function badge(?Member $member, string $l): string
    {
        if ($member === null) {
            return '?';
        }

        $parts = [trim(e($member->display_name).' ✅ '.($member->verified_at?->format('M Y') ?? ''))];

        if ((int) $member->rating_count > 0) {
            $parts[] = '⭐ '.number_format((float) $member->rating_avg, 1).' ('.$member->rating_count.')';
        }

        $parts[] = (int) $member->trips_completed > 0
            ? $this->messages->text('goods_badge_deals', $l, ['count' => $member->trips_completed])
            : $this->messages->text('goods_badge_new', $l);

        return implode(' · ', $parts);
    }

    /** The /profile line: deals and stars. */
    public function profileLine(Member $member): string
    {
        $l = $member->language;
        $rating = (int) $member->rating_count > 0
            ? '⭐ '.number_format((float) $member->rating_avg, 1).' ('.$member->rating_count.')'
            : $this->messages->text('goods_no_rating', $l);

        return $this->messages->text('goods_profile_line', $l, ['deals' => (int) $member->trips_completed, 'rating' => $rating]);
    }

    public function route(GoodsPost $post, string $l): string
    {
        return Choices::goodsCity($post->city_from, $l).' → '.Choices::goodsCity($post->city_to, $l);
    }

    /** The goods group's copy: label language, the rules in all three languages, a status tag once closed. */
    public function groupText(GoodsPost $post): string
    {
        $label = Community::labelLanguage();
        $tag = match ($post->status) {
            'matched' => $this->messages->text('goods_tag_matched', $label)."\n",
            'expired' => $this->messages->text('goods_tag_expired', $label)."\n",
            'closed' => $this->messages->text('goods_tag_closed', $label)."\n",
            default => '',
        };

        $rules = array_unique(array_map(fn (string $l) => $this->messages->text('goods_card_rules', $l), Choices::LANGUAGES));

        return $tag.$this->card($post, $label)."\n\n".implode("\n", $rules);
    }

    private function groupKeyboard(GoodsPost $post): array
    {
        $label = $this->messages->button('btn_goods_interested', Community::labelLanguage());
        $link = $this->telegram->startLink('g'.$post->id);

        return ['inline_keyboard' => [[$link !== null ? ['text' => $label, 'url' => $link] : ['text' => $label, 'callback_data' => 'g:i:'.$post->id]]]];
    }

    private function postToGroup(GoodsPost $post): bool
    {
        $group = Community::goodsGroup();
        if ($group === '') {
            return false;
        }

        try {
            $message = $this->telegram->sendToGroup($group, $this->groupText($post), null, $this->groupKeyboard($post));
        } catch (\Throwable) {
            return false;
        }

        $post->forceFill(['group_message_id' => $message['message_id'] ?? null])->save();

        return true;
    }

    /** Bring the group copy in line with the post: new kilos, a status tag, no button once closed. */
    public function refreshGroupCard(?GoodsPost $post): void
    {
        $group = Community::goodsGroup();
        if ($post === null || $group === '' || ! $post->group_message_id) {
            return;
        }

        $post->loadMissing('member');

        if ($post->status === 'hidden') {
            $this->telegram->deleteMessage($group, (int) $post->group_message_id);

            return;
        }

        $this->telegram->editText($group, (int) $post->group_message_id, $this->groupText($post),
            $post->isOpen() ? $this->groupKeyboard($post) : ['inline_keyboard' => []]);
    }

    // ---- helpers -------------------------------------------------------------------------------------------

    /** 20/11/2026, 2026-11-20, 20.11.2026, or 20/11 (this year, or next if it has passed); today up to four months ahead. */
    public static function parseDate(string $text): ?CarbonImmutable
    {
        $text = preg_replace('/\s+/', '', $text) ?? '';
        $today = CarbonImmutable::parse(GoodsPost::today());

        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $text, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $text, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/.-](\d{1,2})$#', $text, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], $today->year];
            if (checkdate($mo, $d, $y) && CarbonImmutable::create($y, $mo, $d)->lessThan($today)) {
                $y++;
            }
        } else {
            return null;
        }

        if (! checkdate($mo, $d, $y)) {
            return null;
        }

        $date = CarbonImmutable::create($y, $mo, $d);

        return $date->greaterThanOrEqualTo($today) && $date->lessThanOrEqualTo($today->addDays(GoodsPost::MAX_DAYS_AHEAD)) ? $date : null;
    }

    private function mention(Member $member): string
    {
        $link = '<a href="tg://user?id='.(int) $member->telegram_user_id.'">'.e($member->display_name).'</a>';

        return $member->telegram_username ? $link.' (@'.e($member->telegram_username).')' : $link;
    }

    private function dealKeyboard(GoodsMatch $match, string $l): array
    {
        return ['inline_keyboard' => [[
            ['text' => $this->messages->button('btn_goods_agree', $l), 'callback_data' => 'g:ok:'.$match->id],
            ['text' => $this->messages->button('btn_goods_drop', $l), 'callback_data' => 'g:x:'.$match->id],
        ]]];
    }

    private function askDirection(Member $member): void
    {
        $l = $member->language;
        $this->telegram->sendText($member->chat_id, $this->messages->text('goods_ask_direction', $l),
            $this->telegram->replyKeyboard([...Choices::rows(Choices::GOODS_DIRECTIONS, $l, 1), [$this->messages->button('btn_cancel', $l)]]));
    }

    private function askDate(Member $member, string $kind): void
    {
        $l = $member->language;
        $this->telegram->sendText($member->chat_id, $this->messages->text('goods_ask_date_'.$kind, $l), $this->cancelKeyboard($l));
    }

    private function askCity(Member $member, string $direction, bool $from): void
    {
        $l = $member->language;
        $this->telegram->sendText($member->chat_id, $this->messages->text($from ? 'goods_ask_from' : 'goods_ask_to', $l),
            $this->telegram->replyKeyboard([...Choices::rows(Choices::goodsCities($direction, $from), $l), [$this->messages->button('btn_cancel', $l)]]));
    }

    private function askKilos(Member $member, string $kind): void
    {
        $l = $member->language;
        $this->telegram->sendText($member->chat_id, $this->messages->text('goods_ask_kilos_'.$kind, $l), $this->kilosKeyboard($l, $kind));
    }

    private function kilosKeyboard(string $l, string $kind): array
    {
        $steps = $kind === 'traveller' ? Choices::GOODS_KILOS_TRAVELLER : Choices::GOODS_KILOS_SENDER;
        $labels = array_map(fn (int $k) => $this->messages->button('btn_goods_kilos', $l, ['kilos' => $k]), $steps);

        return $this->telegram->replyKeyboard([...array_chunk($labels, 3), [$this->messages->button('btn_cancel', $l)]]);
    }

    private function categoriesKeyboard(string $l): array
    {
        return $this->telegram->replyKeyboard([
            ...Choices::rows(Choices::GOODS_CATEGORIES, $l),
            [$this->messages->button('btn_done', $l)],
            [$this->messages->button('btn_cancel', $l)],
        ], oneTime: false);
    }

    private function askText(Member $member, string $kind, ?string $problem = null): void
    {
        $l = $member->language;
        $question = $this->messages->text($kind === 'traveller' ? 'goods_ask_terms' : 'goods_ask_description', $l);
        $text = $problem !== null ? $this->messages->text($problem, $l)."\n\n".$question : $question;
        $rows = $kind === 'traveller'
            ? [[$this->messages->button('btn_skip', $l)], [$this->messages->button('btn_cancel', $l)]]
            : [[$this->messages->button('btn_cancel', $l)]];

        $this->telegram->sendText($member->chat_id, $text, $this->telegram->replyKeyboard($rows));
    }

    private function cancelKeyboard(string $l): array
    {
        return $this->telegram->replyKeyboard([[$this->messages->button('btn_cancel', $l)]], oneTime: false);
    }

    private function say(Member $member, string $key, array $vars = [], ?array $markup = null): void
    {
        $this->telegram->sendText($member->chat_id, $this->messages->text($key, $member->language, $vars), $markup);
    }

    /** A message to the other side of a match, who may have blocked the bot: never fails the caller. */
    private function tell(?Member $member, string $key, array $vars = []): void
    {
        if (! $member?->chat_id) {
            return;
        }

        try {
            $this->telegram->sendText($member->chat_id, $this->messages->text($key, $member->language, $vars));
        } catch (\Throwable) {
        }
    }
}
