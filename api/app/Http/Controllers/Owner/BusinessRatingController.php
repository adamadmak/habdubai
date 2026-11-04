<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessRating;
use App\Models\Member;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stars for a place, from the website. Verified members only, one set per
 * member per place, and they may change them. An owner cannot rate their own.
 */
class BusinessRatingController extends Controller
{
    /** The stars this member already gave, and whether they may give any. */
    public function show(Request $request, TelegramBotService $telegram, string $slug): JsonResponse
    {
        $business = $this->listed($slug);
        $member = $request->user()->communityMember();

        return response()->json($this->answer($business, $member) + ['bot_link' => $telegram->startLink()]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate(['stars' => ['required', 'integer', 'min:'.BusinessRating::MIN_STARS, 'max:'.BusinessRating::MAX_STARS]]);
        $business = $this->listed($slug);
        $member = $request->user()->communityMember();

        $status = $member === null ? 'not_member' : BusinessRating::give($member, $business, (int) $data['stars']);

        return response()->json(['status' => $status] + $this->answer($business, $member) + $business->ratingPayload(), $status === 'saved' ? 200 : 403);
    }

    private function listed(string $slug): Business
    {
        $business = Business::query()->listed()->where('slug', $slug)->first();
        abort_if($business === null, 404, 'This business is not listed.');

        return $business;
    }

    /** @return array{can_rate: bool, blocked_by: ?string, stars: ?int} */
    private function answer(Business $business, ?Member $member): array
    {
        $blockedBy = match (true) {
            $member === null || ! $member->isActive() => 'not_member',
            $business->isOwnedBy($member) => 'own',
            default => null,
        };

        return [
            'can_rate' => $blockedBy === null,
            'blocked_by' => $blockedBy,
            'stars' => $member ? BusinessRating::query()->where('business_id', $business->id)->where('member_id', $member->id)->value('stars') : null,
        ];
    }
}
