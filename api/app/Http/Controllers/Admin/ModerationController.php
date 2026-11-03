<?php

namespace App\Http\Controllers\Admin;

use App\Bot\GroupModerator;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GroupReport;
use App\Models\Member;
use App\Models\MemberStrike;
use App\Services\TelegramBotService;
use App\Support\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The moderator queue for messages reported in the groups, and the strike history. */
class ModerationController extends Controller
{
    public function index(): JsonResponse
    {
        $open = GroupReport::query()->with(['reporter:id,display_name', 'reported:id,display_name,role,verified_at,muted_until,banned_at'])
            ->where('status', 'open')->oldest('id')->get()
            ->groupBy(fn (GroupReport $r) => $r->chat_id.':'.$r->message_id);

        $groups = [Community::questionsGroup() => 'Questions group', Community::goodsGroup() => 'Goods group'];

        $items = $open->map(function ($reports) use ($groups) {
            /** @var GroupReport $first */
            $first = $reports->first();
            $reported = $first->reported;

            return [
                'chat_id' => $first->chat_id,
                'message_id' => $first->message_id,
                'group' => $groups[(string) $first->chat_id] ?? (string) $first->chat_id,
                'excerpt' => $first->excerpt,
                'reports' => $reports->count(),
                'reporters' => $reports->map(fn (GroupReport $r) => $r->reporter?->display_name)->filter()->values(),
                'hidden' => $reports->contains(fn (GroupReport $r) => $r->action === 'hidden'),
                'reported' => $reported ? [
                    'id' => $reported->id,
                    'name' => $reported->display_name,
                    'role' => $reported->role,
                    'muted_until' => $reported->muted_until?->toIso8601String(),
                    'banned' => $reported->banned_at !== null,
                    'strikes_90d' => MemberStrike::query()->where('member_id', $reported->id)->where('created_at', '>=', now()->subDays(90))->count(),
                ] : null,
                'first_reported_at' => $first->created_at?->toIso8601String(),
            ];
        })->values();

        $strikes = MemberStrike::query()->with(['member:id,display_name', 'member'])->latest('id')->limit(50)->get()->map(fn (MemberStrike $s) => [
            'id' => $s->id,
            'member' => $s->member ? ['id' => $s->member->id, 'name' => $s->member->display_name] : null,
            'kind' => $s->kind,
            'reason' => $s->reason,
            'until' => $s->until?->toIso8601String(),
            'by_admin' => $s->actor_id !== null,
            'created_at' => $s->created_at?->toIso8601String(),
        ]);

        return response()->json(['queue' => $items, 'strikes' => $strikes]);
    }

    public function resolve(Request $request, TelegramBotService $telegram, GroupModerator $moderator): JsonResponse
    {
        $data = $request->validate([
            'chat_id' => ['required', 'integer'],
            'message_id' => ['required', 'integer'],
            'action' => ['required', Rule::in(['dismiss', 'delete', 'warn', 'mute1', 'mute7', 'ban'])],
            'reason' => ['nullable', 'string', 'max:250'],
        ]);

        $reports = GroupReport::query()->where('chat_id', $data['chat_id'])->where('message_id', $data['message_id'])->where('status', 'open')->get();
        abort_if($reports->isEmpty(), 404, 'There are no open reports on that message.');

        $reported = $reports->first()->reported_member_id ? Member::query()->find($reports->first()->reported_member_id) : null;
        $reason = $data['reason'] ?: 'A message you posted broke the community rules.';
        $actor = $request->user()->id;

        if ($data['action'] !== 'dismiss') {
            $telegram->deleteMessage($data['chat_id'], $data['message_id']);
        }

        if ($reported) {
            match ($data['action']) {
                'warn' => $moderator->warn($reported, $reason, $actor),
                'mute1' => $moderator->mute($reported, now()->addDay(), $reason, $actor),
                'mute7' => $moderator->mute($reported, now()->addDays(7), $reason, $actor),
                'ban' => $this->ban($reported, $reason, $actor, $telegram),
                default => null,
            };
        }

        GroupReport::query()->whereIn('id', $reports->pluck('id'))->update([
            'status' => 'closed', 'action' => $data['action'], 'closed_by' => $actor, 'closed_at' => now(),
        ]);
        AuditLog::record($actor, 'moderation.resolved', 'member', $reported?->id, null, $data);

        return response()->json(['ok' => true]);
    }

    private function ban(Member $member, string $reason, int $actor, TelegramBotService $telegram): void
    {
        $member->forceFill(['banned_at' => now(), 'ban_reason' => $reason])->save();

        foreach (array_filter([Community::questionsGroup(), Community::goodsGroup()]) as $group) {
            $telegram->banFromChat($group, (int) $member->telegram_user_id);
        }

        AuditLog::record($actor, 'member.banned', 'member', $member->id, null, ['reason' => $reason]);
    }
}
