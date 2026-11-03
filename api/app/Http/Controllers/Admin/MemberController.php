<?php

namespace App\Http\Controllers\Admin;

use App\Bot\Choices;
use App\Http\Controllers\Controller;
use App\Support\Search;
use App\Models\AuditLog;
use App\Models\Member;
use App\Services\TelegramBotService;
use App\Support\RuntimeEnv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Member::query()->with('trades')->latest('verified_at');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search): void {
                // Case-insensitive on Postgres too, where plain like is not.
                Search::anyColumn($q, ['display_name', 'telegram_username'], $search)
                    ->orWhere('phone_last4', $search);

                if (ctype_digit($search)) {
                    $q->orWhere('telegram_user_id', (int) $search)->orWhere('id', (int) $search);
                }
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        match ($request->query('status')) {
            'active' => $query->whereNotNull('agreed_rules_at')->whereNull('banned_at')->whereNull('left_at'),
            'onboarding' => $query->whereNull('agreed_rules_at'),
            'needs_review' => $query->where('needs_review', true),
            'banned' => $query->whereNotNull('banned_at'),
            'left' => $query->whereNotNull('left_at'),
            default => null,
        };

        if ($emirate = $request->query('emirate')) {
            $query->where('emirate', $emirate);
        }

        $page = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Member $m) => $this->payload($m))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
            'choices' => ['emirates' => Choices::EMIRATES, 'trades' => Choices::TRADES, 'roles' => Member::ROLES],
        ]);
    }

    public function show(Member $member): JsonResponse
    {
        $member->load('trades');

        return response()->json([
            'member' => $this->payload($member),
            'audit' => AuditLog::query()->where('subject_type', 'member')->where('subject_id', $member->id)->latest('created_at')->limit(50)->get(),
        ]);
    }

    public function update(Request $request, Member $member): JsonResponse
    {
        $data = $request->validate([
            'role' => ['sometimes', Rule::in(Member::ROLES)],
            'needs_review' => ['sometimes', 'boolean'],
            'display_name' => ['sometimes', 'string', 'min:2', 'max:40'],
        ]);

        $before = $member->only(array_keys($data));
        $member->forceFill($data)->save();
        AuditLog::record($request->user()->id, 'member.updated', 'member', $member->id, $before, $data);

        return response()->json(['member' => $this->payload($member->fresh('trades'))]);
    }

    public function ban(Request $request, Member $member, TelegramBotService $telegram): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        $member->forceFill(['banned_at' => now(), 'ban_reason' => $data['reason'] ?? null])->save();
        AuditLog::record($request->user()->id, 'member.banned', 'member', $member->id, null, ['reason' => $data['reason'] ?? null]);

        foreach (['telegram_questions_group_id', 'telegram_goods_group_id'] as $key) {
            $groupId = RuntimeEnv::get(null, $key, null);
            if ($groupId !== '') {
                $telegram->banFromChat($groupId, (int) $member->telegram_user_id);
            }
        }

        return response()->json(['member' => $this->payload($member->fresh('trades'))]);
    }

    public function unban(Request $request, Member $member): JsonResponse
    {
        $member->forceFill(['banned_at' => null, 'ban_reason' => null])->save();
        AuditLog::record($request->user()->id, 'member.unbanned', 'member', $member->id);

        return response()->json(['member' => $this->payload($member->fresh('trades'))]);
    }

    private function payload(Member $m): array
    {
        return [
            'id' => $m->id,
            'telegram_user_id' => (string) $m->telegram_user_id,
            'telegram_username' => $m->telegram_username,
            'display_name' => $m->display_name,
            'phone_last4' => $m->phone_last4,
            'phone_country' => $m->phone_country,
            'needs_review' => $m->needs_review,
            'emirate' => $m->emirate,
            'trades' => $m->relationLoaded('trades') ? $m->tradeKeys() : [],
            'language' => $m->language,
            'role' => $m->role,
            'status' => $m->isBanned() ? 'banned' : ($m->hasLeft() ? 'left' : ($m->agreed_rules_at ? 'active' : 'onboarding')),
            'verified_at' => $m->verified_at?->toIso8601String(),
            'agreed_rules_at' => $m->agreed_rules_at?->toIso8601String(),
            'left_at' => $m->left_at?->toIso8601String(),
            'banned_at' => $m->banned_at?->toIso8601String(),
            'ban_reason' => $m->ban_reason,
            'helper_points' => $m->helper_points,
            'trips_completed' => $m->trips_completed,
            'rating_avg' => $m->rating_avg,
            'rating_count' => $m->rating_count,
        ];
    }
}
