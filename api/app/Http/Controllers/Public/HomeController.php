<?php

namespace App\Http\Controllers\Public;

use App\Bot\Choices;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Business;
use App\Models\GoodsPost;
use App\Models\Job;
use App\Models\Member;
use App\Services\TelegramBotService;
use App\Support\RuntimeEnv;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** The website's front page in one request, and the links every page needs. */
class HomeController extends Controller
{
    public function home(TelegramBotService $telegram): JsonResponse
    {
        // Plain arrays only: the cache refuses to restore objects it was not told about.
        $home = Cache::remember('public.home.v2', 60, function (): array {
            $businesses = Business::query()->listed()->with(['city', 'photos' => fn ($q) => $q->where('approved', true)->select(['id', 'business_id', 'kind', 'approved', 'updated_at'])])
                ->orderByDesc('reviewed_at')->limit(24)->get()
                // Listings with a photo lead the row.
                ->sortBy(fn (Business $b) => $b->cover() === null ? 1 : 0)->take(8)->values();

            $today = now('Asia/Dubai')->toDateString();
            $byCategory = Business::query()->listed()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category');

            return [
                'stats' => [
                    'members' => Member::query()->whereNotNull('agreed_rules_at')->whereNull('banned_at')->whereNull('left_at')->count(),
                    'jobs' => Job::query()->where('status', 'published')->count(),
                    'businesses' => Business::query()->listed()->count(),
                    'goods' => Schema::hasTable('goods_posts') ? GoodsPost::query()->live()->count() : 0,
                ],
                'jobs' => Job::query()->with('source:id,name')->where('status', 'published')->orderByDesc('published_at')->limit(8)->get()
                    ->map(fn (Job $j) => JobController::payload($j))->values()->all(),
                'businesses' => $businesses->map(fn (Business $b) => $b->publicPayload())->values()->all(),
                'events' => Announcement::query()->with('topic')->where('status', 'published')->whereNotNull('event_date')
                    ->whereDate('event_date', '>=', $today)->orderBy('event_date')->limit(4)->get()
                    ->map(fn (Announcement $a) => AnnouncementController::payload($a))->values()->all(),
                'announcements' => Announcement::query()->with('topic')->where('status', 'published')->orderByDesc('published_at')->limit(6)->get()
                    ->map(fn (Announcement $a) => AnnouncementController::payload($a))->values()->all(),
                'categories' => collect(Choices::BUSINESS_CATEGORIES)
                    ->map(fn (array $labels, string $key) => ['key' => $key, 'name' => $labels, 'count' => (int) ($byCategory[$key] ?? 0)])
                    ->values()->all(),
            ];
        });

        return response()->json($home + ['meta' => $this->metaPayload($telegram)])->header('Cache-Control', 'public, max-age=60');
    }

    public function meta(TelegramBotService $telegram): JsonResponse
    {
        return response()->json($this->metaPayload($telegram))->header('Cache-Control', 'public, max-age=300');
    }

    /** Where to join: the bot, the channel and the groups, as the admin set them. */
    private function metaPayload(TelegramBotService $telegram): array
    {
        $support = ltrim(RuntimeEnv::get(null, 'support_telegram_username', null), '@');

        return [
            'bot' => ['username' => $telegram->botUsername() ?: null, 'link' => $telegram->startLink()],
            'channel' => RuntimeEnv::get(null, 'telegram_channel_link', null) ?: null,
            'questions_group' => RuntimeEnv::get(null, 'telegram_questions_group_link', null) ?: null,
            'goods_group' => RuntimeEnv::get(null, 'telegram_goods_group_link', null) ?: null,
            'support' => $support !== '' ? 'https://t.me/'.$support : null,
            // The bot's own wording for every fixed list, so the website never drifts from it.
            'labels' => [
                'trades' => Choices::TRADES,
                'emirates' => Choices::JOB_EMIRATES,
                'business_categories' => Choices::BUSINESS_CATEGORIES,
                'languages' => Choices::SPOKEN_LANGUAGES,
            ],
        ];
    }
}
