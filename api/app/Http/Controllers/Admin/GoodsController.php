<?php

namespace App\Http\Controllers\Admin;

use App\Bot\Choices;
use App\Bot\GoodsFlow;
use App\Http\Controllers\Controller;
use App\Support\Search;
use App\Models\GoodsMatch;
use App\Models\GoodsPost;
use App\Models\Member;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The goods board for moderators: every post, every match, and the disputes
 * a "no" at check-in opens. A moderator decides a dispute as delivered,
 * failed (naming the member at fault) or cancelled; two failures in a year
 * take a member off the board.
 */
class GoodsController extends Controller
{
    private const MEMBER_FIELDS = 'id,display_name,telegram_username,verified_at,trips_completed,rating_avg,rating_count';

    /** @var array<int, int>|null failed deals in the last year per member id, read once per page */
    private ?array $failures = null;

    public function posts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'open', 'matched', 'expired', 'closed', 'hidden'])],
            'kind' => ['nullable', Rule::in(GoodsPost::KINDS)],
            'direction' => ['nullable', Rule::in(GoodsPost::DIRECTIONS)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = GoodsPost::query()->with('member:'.self::MEMBER_FIELDS)->latest('id')
            ->when(($data['status'] ?? 'all') !== 'all', fn ($q) => $q->where('status', $data['status']))
            ->when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($data['direction'] ?? null, fn ($q, $direction) => $q->where('direction', $direction))
            ->when(trim((string) ($data['search'] ?? '')), fn ($q, $search) => $q->where(fn ($w) => $w
                ->where(fn ($d) => Search::anyColumn($d, ['description'], $search))
                ->orWhereHas('member', fn ($m) => Search::anyColumn($m, ['display_name'], $search))));

        $page = $query->paginate(25);
        $this->loadFailures($page->getCollection()->pluck('member_id'));

        return response()->json([
            'data' => $page->getCollection()->map(fn (GoodsPost $p) => $this->post($p))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'counts' => GoodsPost::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function hide(Request $request, GoodsPost $post, GoodsFlow $goods): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:250']]);

        if ($post->status === 'hidden') {
            throw ValidationException::withMessages(['reason' => 'This post is already hidden.']);
        }

        $goods->hide($post->load('member'), $data['reason'], $request->user()->id);

        return response()->json(['post' => $this->post($post->fresh('member'))]);
    }

    public function matches(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'live', 'requested', 'introduced', 'agreed', 'disputed', 'completed', 'failed', 'cancelled', 'declined', 'expired', 'unconfirmed'])],
        ]);
        $status = $data['status'] ?? 'all';

        $query = GoodsMatch::query()
            ->with(['post.member:'.self::MEMBER_FIELDS, 'requesterPost.member:'.self::MEMBER_FIELDS, 'ratings'])
            ->when($status === 'live', fn ($q) => $q->whereIn('status', GoodsMatch::LIVE))
            ->when(! in_array($status, ['all', 'live'], true), fn ($q) => $q->where('status', $status))
            // Disputes oldest first: the longest wait is decided first.
            ->when($status === 'disputed', fn ($q) => $q->oldest('updated_at'), fn ($q) => $q->latest('id'));

        $page = $query->paginate(25);
        $this->loadFailures($page->getCollection()->flatMap(fn (GoodsMatch $m) => [$m->owner_id, $m->requester_id]));

        return response()->json([
            'data' => $page->getCollection()->map(fn (GoodsMatch $m) => $this->match($m))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'counts' => GoodsMatch::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function resolve(Request $request, GoodsMatch $match, GoodsFlow $goods): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['completed', 'failed', 'cancelled'])],
            'failed_member_id' => ['nullable', 'integer', Rule::requiredIf($request->input('outcome') === 'failed'),
                Rule::in([$match->owner_id, $match->requester_id])],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'failed_member_id.required' => 'Choose which member is at fault.',
            'failed_member_id.in' => 'The member at fault must be one of the two sides of this match.',
        ]);

        if (! in_array($match->status, ['agreed', 'disputed', 'unconfirmed'], true)) {
            throw ValidationException::withMessages(['outcome' => 'Only an agreed, disputed or unconfirmed match can be decided.']);
        }

        $goods->resolve($match, $data['outcome'], $data['failed_member_id'] ?? null, $data['note'] ?? null, $request->user()->id);

        return response()->json(['match' => $this->match($match->fresh(['post.member', 'requesterPost.member', 'ratings']))]);
    }

    private function post(GoodsPost $post): array
    {
        return [
            'id' => $post->id,
            'kind' => $post->kind,
            'direction' => $post->direction,
            'route' => Choices::goodsCity($post->city_from, 'en').' → '.Choices::goodsCity($post->city_to, 'en'),
            'date' => $post->date?->toDateString(),
            'kilos' => $post->kilos,
            'kilos_left' => $post->kilos_left,
            'categories' => array_map(fn (string $c) => Choices::label(Choices::GOODS_CATEGORIES, $c, 'en'), (array) $post->categories),
            'description' => $post->description,
            // The carry fee members pay each other, and whether the parcel is handed over at the other end.
            'price' => $post->price !== null ? (int) $post->price : null,
            'currency' => $post->price !== null && (int) $post->price > 0 ? $post->currency : null,
            'mode' => $post->isTraveller() ? null : ($post->mode ?? 'send'),
            'status' => $post->status,
            'outcome' => $post->outcome,
            'closed_at' => $post->closed_at?->toIso8601String(),
            'open' => $post->isOpen(),
            'hidden_reason' => $post->hidden_reason,
            'in_group' => $post->group_message_id !== null,
            'member' => $this->member($post->member),
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }

    private function match(GoodsMatch $match): array
    {
        $trip = $match->travellerPost();
        $parcel = $match->senderPost();

        return [
            'id' => $match->id,
            'status' => $match->status,
            'route' => $trip ? Choices::goodsCity($trip->city_from, 'en').' → '.Choices::goodsCity($trip->city_to, 'en') : null,
            'flight_date' => $trip?->date?->toDateString(),
            'kilos' => $match->kilos,
            'parcel' => $parcel?->description,
            'traveller' => $this->member($trip?->member),
            'sender' => $this->member($parcel?->member),
            'owner_id' => $match->owner_id,
            'requester_id' => $match->requester_id,
            'checkin_on' => $match->checkin_on?->toDateString(),
            'checkin' => [
                'traveller' => $trip && $trip->member_id === $match->owner_id ? $match->checkin_owner : $match->checkin_requester,
                'sender' => $trip && $trip->member_id === $match->owner_id ? $match->checkin_requester : $match->checkin_owner,
            ],
            'ratings' => $match->ratings->map(fn (Rating $r) => [
                'from_member_id' => $r->from_member_id,
                'stars' => $r->stars,
                'visible' => $r->visible_at <= now(),
            ])->values(),
            'failed_member_id' => $match->failed_member_id,
            'moderator_note' => $match->moderator_note,
            'introduced_at' => $match->introduced_at?->toIso8601String(),
            'agreed_at' => $match->agreed_at?->toIso8601String(),
            'closed_at' => $match->closed_at?->toIso8601String(),
            'created_at' => $match->created_at?->toIso8601String(),
        ];
    }

    private function member(?Member $member): ?array
    {
        if ($member === null) {
            return null;
        }

        return [
            'id' => $member->id,
            'name' => $member->display_name,
            'username' => $member->telegram_username,
            'verified_at' => $member->verified_at?->toIso8601String(),
            'deals' => (int) $member->trips_completed,
            'rating_avg' => $member->rating_avg !== null ? (float) $member->rating_avg : null,
            'rating_count' => (int) $member->rating_count,
            'failures_year' => $this->failures !== null ? ($this->failures[$member->id] ?? 0) : GoodsMatch::failuresFor($member),
        ];
    }

    /** One query for the whole page instead of one per member shown (the same rule as failuresFor). */
    private function loadFailures(iterable $memberIds): void
    {
        $ids = collect($memberIds)->filter()->unique()->values()->all();

        $this->failures = $ids === [] ? [] : GoodsMatch::query()
            ->whereIn('failed_member_id', $ids)->where('status', 'failed')->where('closed_at', '>=', now()->subYear())
            ->selectRaw('failed_member_id, count(*) as n')->groupBy('failed_member_id')
            ->pluck('n', 'failed_member_id')->map(fn ($n) => (int) $n)->all();
    }
}
