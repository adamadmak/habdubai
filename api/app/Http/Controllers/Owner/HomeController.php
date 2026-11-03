<?php

namespace App\Http\Controllers\Owner;

use App\Bot\Choices;
use App\Http\Controllers\Controller;
use App\Models\BusinessOwner;
use App\Models\GoodsMatch;
use App\Models\GoodsPost;
use App\Models\JobFilter;
use App\Models\JobSave;
use App\Models\Member;
use App\Models\Question;
use App\Services\TelegramBotService;
use App\Support\RuntimeEnv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The member's own page on the website: who they are in the community and
 * what they have going on — goods posts and deals, questions, saved jobs,
 * business listings. Read only; everything is done where it was started
 * (the bot, the goods pages, the listings pages).
 *
 * An account that is not linked to an active member (someone who signed in
 * to list a business, or who left) gets the listings part only, with the bot
 * link to join.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, TelegramBotService $telegram): JsonResponse
    {
        /** @var BusinessOwner $owner */
        $owner = $request->user();
        $member = $owner->member_id ? Member::query()->with('trades')->find($owner->member_id) : null;
        if ($member && ! $member->isActive()) {
            $member = null;
        }

        $setting = fn (string $key): ?string => RuntimeEnv::get(null, $key, null) ?: null;

        return response()->json([
            'owner' => $owner->payload(),
            'member' => $member ? self::memberPayload($member) : null,
            'goods' => $member ? [
                'open_posts' => GoodsPost::query()->where('member_id', $member->id)->where('status', 'open')->count(),
                'live_deals' => GoodsMatch::query()->whereIn('status', GoodsMatch::LIVE)
                    ->where(fn ($q) => $q->where('owner_id', $member->id)->orWhere('requester_id', $member->id))->count(),
                'completed' => (int) $member->trips_completed,
            ] : null,
            'questions' => $member ? [
                'total' => Question::query()->where('member_id', $member->id)->count(),
                'open' => Question::query()->where('member_id', $member->id)->where('status', 'open')->count(),
                'latest' => Question::query()->withCount('replies')->where('member_id', $member->id)->latest('id')->limit(3)->get()
                    ->map(fn (Question $q) => [
                        'id' => $q->id,
                        'title' => $q->title,
                        'status' => $q->status,
                        'replies' => (int) $q->replies_count,
                        'created_at' => $q->created_at?->toIso8601String(),
                    ])->values(),
            ] : null,
            'jobs' => $member ? [
                'alerts_on' => (bool) JobFilter::query()->where('member_id', $member->id)->value('alerts_on'),
                'saved_count' => JobSave::query()->where('member_id', $member->id)->count(),
                'saved' => JobSave::query()->with('job')->where('member_id', $member->id)->latest('created_at')->limit(5)->get()
                    ->map(fn (JobSave $save) => $save->job ? [
                        'id' => $save->job->id,
                        'title' => $save->job->title,
                        'company' => $save->job->company,
                        'emirate' => $save->job->emirate,
                        'open' => $save->job->status === 'published',
                    ] : null)->filter()->values(),
            ] : null,
            'businesses' => [
                'approved' => $owner->businesses()->where('status', 'approved')->count(),
                'pending' => $owner->businesses()->where('status', 'pending')->count(),
                'claims_pending' => $owner->claims()->where('status', 'pending')->count(),
            ],
            'links' => [
                'bot' => $telegram->startLink(),
                'channel' => $setting('telegram_channel_link'),
                'questions_group' => $setting('telegram_questions_group_link'),
                'goods_group' => $setting('telegram_goods_group_link'),
            ],
        ]);
    }

    /** The member as the website shows them to themselves: keys, not labels, so the page can word them in its own language. */
    public static function memberPayload(Member $member): array
    {
        return [
            'name' => $member->display_name,
            'emirate' => $member->emirate,
            'trades' => $member->tradeKeys(),
            'language' => $member->language,
            'role' => $member->role,
            'verified_since' => $member->verified_at?->toIso8601String(),
            'helper_points' => (int) $member->helper_points,
            'rank' => Choices::rank((int) $member->helper_points),
            'rating' => (int) $member->rating_count > 0 ? ['avg' => round((float) $member->rating_avg, 1), 'count' => (int) $member->rating_count] : null,
            'trips_completed' => (int) $member->trips_completed,
        ];
    }
}
