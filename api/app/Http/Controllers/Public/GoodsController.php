<?php

namespace App\Http\Controllers\Public;

use App\Bot\Choices;
use App\Http\Controllers\Controller;
use App\Models\GoodsMatch;
use App\Models\GoodsPost;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * The goods board on the website: open trips and parcels with the poster's
 * trust badge (name, verified since, stars, deals) and nothing that reaches
 * them. Contact happens in Telegram, after the owner accepts a request.
 */
class GoodsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'direction' => ['nullable', 'in:'.implode(',', GoodsPost::DIRECTIONS)],
            'kind' => ['nullable', 'in:'.implode(',', GoodsPost::KINDS)],
            'mode' => ['nullable', 'in:'.implode(',', GoodsPost::MODES)],
            'page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        // This host migrates on deploy, but a board that is not there yet answers empty, not 500.
        if (! Schema::hasTable('goods_posts')) {
            return response()->json(['data' => [], 'meta' => ['total' => 0, 'page' => 1, 'last_page' => 1], 'stats' => self::emptyStats(), 'choices' => self::choices()]);
        }

        $page = self::board()
            ->when($data['direction'] ?? null, fn ($q, $direction) => $q->where('direction', $direction))
            ->when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($data['mode'] ?? null, fn ($q, $mode) => $mode === 'request'
                ? $q->where('mode', 'request')
                : $q->where('kind', 'sender')->where(fn ($s) => $s->whereNull('mode')->orWhere('mode', 'send')))
            ->orderBy('date')->orderBy('id')
            ->paginate(12);

        return response()->json([
            'data' => $page->getCollection()->map(fn (GoodsPost $p) => self::card($p))->values(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'stats' => self::stats(),
            'choices' => self::choices(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    public function show(int $post): JsonResponse
    {
        $found = self::board()->whereKey($post)->first();
        abort_if($found === null || ! $found->isOpen(), 404);

        return response()->json([
            'post' => self::card($found),
            'matches' => $found->candidates(6)->map(fn (GoodsPost $p) => self::card($p))->values(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Before posting: the open posts on the other side that a post like this would pair with
     * (direction, dates within three days, room). The match is shown before anyone commits.
     */
    public function fit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', GoodsPost::KINDS)],
            'direction' => ['required', 'in:'.implode(',', GoodsPost::DIRECTIONS)],
            'date' => ['required', 'date_format:Y-m-d'],
            'kilos' => ['nullable', 'integer', 'min:1', 'max:'.Choices::GOODS_MAX_KILOS],
        ]);

        if (! Schema::hasTable('goods_posts')) {
            return response()->json(['count' => 0, 'posts' => []]);
        }

        $probe = new GoodsPost([
            'member_id' => 0,
            'kind' => $data['kind'],
            'direction' => $data['direction'],
            'date' => $data['date'],
            'kilos' => (int) ($data['kilos'] ?? 1),
            'kilos_left' => $data['kind'] === 'traveller' ? (int) ($data['kilos'] ?? 1) : null,
        ]);
        $fits = $probe->candidates(50);

        return response()->json([
            'count' => $fits->count(),
            'posts' => $fits->take(6)->map(fn (GoodsPost $p) => self::card($p))->values(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /** Open posts of active members, not past their last day. */
    private static function board()
    {
        return GoodsPost::query()->live()->with('member')
            ->whereHas('member', fn ($m) => $m->whereNotNull('agreed_rules_at')->whereNull('banned_at')->whereNull('left_at'));
    }

    /** One post as the website shows it to anyone. */
    public static function card(GoodsPost $post): array
    {
        return [
            'id' => $post->id,
            'kind' => $post->kind,
            'mode' => $post->isTraveller() ? null : ($post->mode ?? 'send'),
            'direction' => $post->direction,
            'date' => $post->date->toDateString(),
            'from' => ['key' => $post->city_from, 'name' => self::city($post->city_from)],
            'to' => ['key' => $post->city_to, 'name' => self::city($post->city_to)],
            'kilos' => (int) $post->kilos,
            'kilos_left' => $post->isTraveller() ? $post->room() : null,
            'categories' => array_values(array_map(fn (string $c) => ['key' => $c, 'name' => Choices::GOODS_CATEGORIES[$c] ?? ['en' => $c]], (array) $post->categories)),
            'description' => $post->description,
            // Agreed and paid between the two members; shown so nobody is surprised.
            'price' => $post->price !== null ? (int) $post->price : null,
            'currency' => $post->price !== null && (int) $post->price > 0 ? $post->currency : null,
            'price_basis' => $post->price !== null ? ($post->isTraveller() ? 'per_kg' : 'total') : null,
            'member' => self::badge($post->member),
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }

    /** What lets members trust each other, and nothing that reaches them. */
    public static function badge(?Member $member): array
    {
        return [
            'name' => (string) $member?->display_name,
            'verified_since' => $member?->verified_at?->format('Y-m'),
            'rating' => (int) $member?->rating_count > 0 ? round((float) $member->rating_avg, 1) : null,
            'ratings' => (int) $member?->rating_count,
            'deals' => (int) $member?->trips_completed,
        ];
    }

    /** @return array{am: string, ti: string, en: string} */
    private static function city(string $key): array
    {
        return Choices::GOODS_CITIES_UAE[$key] ?? Choices::GOODS_CITIES_ET[$key] ?? ['am' => $key, 'ti' => $key, 'en' => $key, 'om' => $key];
    }

    /** The fixed lists the website's form and filters use, from the same place as the bot's buttons. */
    public static function choices(): array
    {
        $cities = [];
        foreach (GoodsPost::DIRECTIONS as $direction) {
            $cities[$direction] = ['from' => Choices::goodsCities($direction, true), 'to' => Choices::goodsCities($direction, false)];
        }

        return [
            'directions' => Choices::GOODS_DIRECTIONS,
            'cities' => $cities,
            'categories' => Choices::GOODS_CATEGORIES,
            'max_kilos' => Choices::GOODS_MAX_KILOS,
            'kilos_traveller' => Choices::GOODS_KILOS_TRAVELLER,
            'kilos_sender' => Choices::GOODS_KILOS_SENDER,
            'max_days_ahead' => GoodsPost::MAX_DAYS_AHEAD,
            'max_open' => GoodsPost::MAX_OPEN,
        ];
    }

    /** @return array{trips: int, parcels: int, deals: int, kilos: int} */
    private static function stats(): array
    {
        // Counted in the database: the board is read on every page of the site, and loading
        // every open post (with its member) to count them grows with the board.
        $byKind = self::board()->toBase()->reorder()
            ->selectRaw('kind, count(*) as n, sum(coalesce(kilos_left, kilos)) as room')
            ->groupBy('kind')->get()->keyBy('kind');

        return [
            'trips' => (int) ($byKind['traveller']->n ?? 0),
            'parcels' => (int) ($byKind['sender']->n ?? 0),
            'deals' => GoodsMatch::query()->where('status', 'completed')->count(),
            'kilos' => (int) ($byKind['traveller']->room ?? 0),
        ];
    }

    private static function emptyStats(): array
    {
        return ['trips' => 0, 'parcels' => 0, 'deals' => 0, 'kilos' => 0];
    }
}
