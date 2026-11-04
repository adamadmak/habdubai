<?php

namespace App\Http\Controllers\Public;

use App\Bot\Choices;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\DirectoryCity;
use App\Support\ClientIp;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Listed businesses for the website: browse by city and kind, search, and one page each. */
class DirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city' => ['nullable', 'string', 'max:48'],
            'category' => ['nullable', 'string', 'max:24'],
            'q' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $city = isset($data['city']) ? DirectoryCity::query()->where('key', $data['city'])->first() : null;

        $query = Business::query()->listed()->with(['city', 'photos' => fn ($q) => $q->where('approved', true)->select(['id', 'business_id', 'kind', 'approved', 'updated_at'])])
            ->when($city, fn (Builder $q) => $q->where('city_id', $city->id))
            ->when($data['category'] ?? null, fn (Builder $q, $category) => $q->where('category', $category))
            ->when(trim((string) ($data['q'] ?? '')), fn (Builder $q, $term) => Search::anyColumn($q, ['name', 'area', 'description'], $term))
            // Members' stars first; among equals, verified, then listings their owners keep up to date, then by name.
            ->bestRatedFirst()->orderByDesc('verified')->orderByRaw('owner_id is null')->orderBy('name');

        $page = $query->paginate(24);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Business $b) => $b->publicPayload())->values(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /** Cities and kinds of business, each with how many are listed. */
    public function facets(): JsonResponse
    {
        $byCity = Business::query()->listed()->selectRaw('city_id, count(*) as n')->groupBy('city_id')->pluck('n', 'city_id');
        $byCategory = Business::query()->listed()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category');

        return response()->json([
            'cities' => DirectoryCity::listed()->map(fn (DirectoryCity $c) => [
                'key' => $c->key,
                'name' => ['en' => $c->name_en, 'am' => $c->name_am, 'ti' => $c->name_ti, 'om' => $c->name_om],
                'count' => (int) ($byCity[$c->id] ?? 0),
            ])->values(),
            'categories' => collect(Choices::BUSINESS_CATEGORIES)->map(fn (array $labels, string $key) => [
                'key' => $key,
                'name' => $labels,
                'count' => (int) ($byCategory[$key] ?? 0),
            ])->values(),
            'languages' => Choices::SPOKEN_LANGUAGES,
        ])->header('Cache-Control', 'public, max-age=60');
    }

    public function show(string $slug): JsonResponse
    {
        // Photo rows without their data: only the link is needed, and the data is hundreds of KB each.
        $photos = fn ($q) => $q->where('approved', true)->select(['id', 'business_id', 'kind', 'approved', 'updated_at']);

        $business = Business::query()->listed()->with(['city', 'photos' => $photos])->where('slug', $slug)->first();
        abort_if($business === null, 404, 'This business is not listed.');

        $nearby = Business::query()->listed()->with(['city', 'photos' => $photos])
            ->whereKeyNot($business->id)->where('city_id', $business->city_id)->where('category', $business->category)
            ->bestRatedFirst()->orderByDesc('verified')->orderBy('name')->limit(4)->get();

        return response()->json([
            'business' => $business->publicPayload(full: true),
            'nearby' => $nearby->map(fn (Business $b) => $b->publicPayload())->values(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * A visit, sent by the page in the visitor's browser (the page itself is
     * cached, so it cannot count). One per visitor per listing every six hours.
     */
    public function view(Request $request, string $slug): JsonResponse
    {
        $id = Business::query()->listed()->where('slug', $slug)->value('id');
        abort_if($id === null, 404);

        if (Cache::add('view:'.$id.':'.sha1(ClientIp::of($request)), 1, now()->addHours(6))) {
            // Without touching updated_at, which the page shows as "last updated".
            DB::table('businesses')->where('id', $id)->increment('views');
        }

        return response()->json(['ok' => true]);
    }
}
