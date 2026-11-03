<?php

namespace App\Http\Controllers\Owner;

use App\Bot\Choices;
use App\Bot\GoodsFlow;
use App\Bot\GroupModerator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\GoodsController as PublicGoods;
use App\Models\BusinessOwner;
use App\Models\GoodsMatch;
use App\Models\GoodsPost;
use App\Models\Member;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The goods board for a signed-in member on the website: their posts and
 * deals, a new trip or parcel, closing one, and "interested". The rules are
 * the bot's own (GoodsFlow): verified members only, three open posts of a
 * kind, interest needs your own post on the other side, and accepting,
 * agreeing and the check-in happen in Telegram.
 */
class GoodsController extends Controller
{
    public function __construct(private readonly GoodsFlow $goods) {}

    public function index(Request $request, TelegramBotService $telegram): JsonResponse
    {
        $member = $this->member($request);

        if ($member === null) {
            return response()->json(['member' => null, 'bot_link' => $telegram->startLink(), 'posts' => [], 'deals' => [], 'limits' => $this->limits(null)]);
        }

        $posts = GoodsPost::query()->with('member')->where('member_id', $member->id)
            ->where(fn ($q) => $q->where('status', 'open')->orWhere('updated_at', '>=', now()->subDays(60)))
            ->withCount(['matches as live_requests' => fn ($q) => $q->whereIn('status', ['requested', 'introduced', 'agreed'])])
            ->orderByDesc('date')->limit(20)->get();

        $deals = GoodsMatch::query()->with(['post.member', 'requesterPost.member', 'owner', 'requester'])
            ->where(fn ($q) => $q->where('owner_id', $member->id)->orWhere('requester_id', $member->id))
            ->latest('id')->limit(20)->get();

        return response()->json([
            'member' => $this->memberPayload($member),
            'bot_link' => $telegram->startLink(),
            'posts' => $posts->map(fn (GoodsPost $p) => $this->mine($p))->values(),
            'deals' => $deals->map(fn (GoodsMatch $m) => $this->deal($m, $member))->filter()->values(),
            'limits' => $this->limits($member),
        ]);
    }

    /** A new trip or parcel, checked as the bot checks it, then posted and announced the same way. */
    public function store(Request $request): JsonResponse
    {
        $member = $this->usableMember($request);
        $kind = (string) $request->input('kind');
        $direction = (string) $request->input('direction');
        $today = GoodsPost::today();
        $last = now('Asia/Dubai')->addDays(GoodsPost::MAX_DAYS_AHEAD)->toDateString();

        $data = $request->validate([
            'kind' => ['required', Rule::in(GoodsPost::KINDS)],
            'direction' => ['required', Rule::in(GoodsPost::DIRECTIONS)],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today, 'before_or_equal:'.$last],
            'from' => ['required', Rule::in(array_keys(Choices::goodsCities($direction, true)))],
            'to' => ['required', Rule::in(array_keys(Choices::goodsCities($direction, false)))],
            'kilos' => ['required', 'integer', 'min:1', 'max:'.Choices::GOODS_MAX_KILOS],
            'categories' => ['required', 'array', 'min:1', 'max:'.count(Choices::GOODS_CATEGORIES)],
            'categories.*' => ['string', Rule::in(array_keys(Choices::GOODS_CATEGORIES))],
            'description' => [$kind === 'sender' ? 'required' : 'nullable', 'string', 'min:5', 'max:300', 'not_regex:#^/#'],
            'mode' => ['nullable', Rule::in(GoodsPost::MODES)],
            'price' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'currency' => ['nullable', 'required_unless:price,null,0', Rule::in(GoodsPost::CURRENCIES)],
            'interest' => ['nullable', 'integer'],
        ], [
            'date.after_or_equal' => __('goods.date_range', ['days' => GoodsPost::MAX_DAYS_AHEAD]),
            'date.before_or_equal' => __('goods.date_range', ['days' => GoodsPost::MAX_DAYS_AHEAD]),
        ]);

        if (filled($data['description'] ?? null) && GroupModerator::bannedItemIn((string) $data['description'])) {
            throw ValidationException::withMessages(['description' => [__('goods.banned_item')]]);
        }

        $refusal = $this->goods->refusal($member, $data['kind'], $data['date']);
        if ($refusal === 'blocked' || $refusal === 'max_open') {
            return $this->refuse($refusal);
        }

        $pending = isset($data['interest']) ? GoodsPost::query()->with('member')->find((int) $data['interest']) : null;
        ['post' => $post, 'others' => $others] = $this->goods->create($member, [
            'kind' => $data['kind'],
            'direction' => $data['direction'],
            'date' => $data['date'],
            'from' => $data['from'],
            'to' => $data['to'],
            'kilos' => $data['kilos'],
            'categories' => array_values(array_unique($data['categories'])),
            'text' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'mode' => $data['mode'] ?? 'send',
            'price' => $data['price'] ?? null,
            'currency' => $data['currency'] ?? null,
        ], $pending?->id);

        $request = null;
        if ($pending !== null) {
            $status = $this->goods->followUp($member, $post, $pending);
            $request = ['status' => $status, 'message' => $this->statusMessage($status, $pending)];
        }

        return response()->json([
            'post' => $this->mine($post->load('member')),
            'request' => $request,
            'matches' => $others->map(fn (GoodsPost $p) => PublicGoods::card($p))->values(),
        ], 201);
    }

    public function close(Request $request, int $post): JsonResponse
    {
        $member = $this->member($request);
        $found = GoodsPost::query()->with('member')->whereKey($post)->first();

        abort_if($member === null || $found === null || $found->member_id !== $member->id, 404);
        $this->goods->closePost($member, $found);

        return response()->json(['post' => $this->mine($found->fresh('member'))]);
    }

    /** "Interested" from the website: the same request the bot sends; the owner answers in Telegram. */
    public function interest(Request $request, int $post): JsonResponse
    {
        $member = $this->usableMember($request);
        $found = GoodsPost::query()->with('member')->find($post);
        $status = $this->goods->ask($member, $found);

        $answer = ['status' => $status, 'message' => $this->statusMessage($status, $found)];
        if ($status === 'need_post') {
            // What their own post must be for the two to pair: the other kind, the same way, close in date.
            $answer['need'] = ['kind' => $found->isTraveller() ? 'sender' : 'traveller', 'direction' => $found->direction, 'date' => $found->date->toDateString()];
        }

        return response()->json($answer);
    }

    // ---- helpers ------------------------------------------------------------------

    /**
     * The community member behind this website account: linked when they signed in, or found
     * by their Telegram id if they joined the community afterwards.
     */
    private function member(Request $request): ?Member
    {
        /** @var BusinessOwner $owner */
        $owner = $request->user();
        $member = $owner->member ?? Member::findByTelegramUser((int) $owner->telegram_user_id);

        if ($member !== null && $owner->member_id !== $member->id) {
            $owner->forceFill(['member_id' => $member->id])->save();
        }

        return $member;
    }

    /** A member who may use the board, or the reason why not, as a 403 the website words. */
    private function usableMember(Request $request): Member
    {
        $member = $this->member($request);

        if ($member === null || ! $member->isActive()) {
            abort($this->refuse('not_member'));
        }
        if (GoodsMatch::blocked($member)) {
            abort($this->refuse('blocked'));
        }

        return $member;
    }

    private function refuse(string $code): JsonResponse
    {
        return response()->json(['message' => __('goods.'.$code, ['max' => GoodsPost::MAX_OPEN]), 'code' => $code], 403);
    }

    private function statusMessage(string $status, ?GoodsPost $post): string
    {
        $key = $status === 'need_post' && $post !== null
            ? 'goods.status_need_post_'.($post->isTraveller() ? 'sender' : 'traveller')
            : 'goods.status_'.$status;

        return __($key, ['name' => (string) $post?->member?->display_name, 'count' => GoodsMatch::REQUESTS_PER_DAY]);
    }

    private function memberPayload(Member $member): array
    {
        return PublicGoods::badge($member) + [
            'active' => $member->isActive(),
            'blocked' => GoodsMatch::blocked($member),
        ];
    }

    private function limits(?Member $member): array
    {
        return [
            'max_open' => GoodsPost::MAX_OPEN,
            'open_traveller' => $member ? GoodsPost::openOf($member, 'traveller')->count() : 0,
            'open_sender' => $member ? GoodsPost::openOf($member, 'sender')->count() : 0,
        ];
    }

    /** One of the member's own posts: the public card, its status, and how many have asked about it. */
    private function mine(GoodsPost $post): array
    {
        return PublicGoods::card($post) + [
            'status' => $post->status,
            'open' => $post->isOpen(),
            // Counted with the list when it was read (withCount), else here.
            'requests' => $post->live_requests !== null
                ? (int) $post->live_requests
                : GoodsMatch::query()->where('post_id', $post->id)->whereIn('status', ['requested', 'introduced', 'agreed'])->count(),
        ];
    }

    private function deal(GoodsMatch $match, Member $member): ?array
    {
        $trip = $match->post?->isTraveller() ? $match->post : $match->requesterPost;
        $trip ??= $match->post;
        if ($trip === null) {
            return null;
        }

        $other = $match->owner_id === $member->id ? $match->requester : $match->owner;
        $card = PublicGoods::card($trip);

        return [
            'id' => $match->id,
            'status' => $match->status,
            'role' => $match->owner_id === $member->id ? 'owner' : 'requester',
            'other_name' => (string) $other?->display_name,
            'route' => ['from' => $card['from']['name'], 'to' => $card['to']['name']],
            'date' => $trip->date->toDateString(),
            'kilos' => $match->kilos !== null ? (int) $match->kilos : null,
        ];
    }
}
