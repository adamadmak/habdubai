<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Translator;
use App\Announcements\AnnouncementPublisher;
use App\Bot\BotMessages;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementTopic;
use App\Models\AuditLog;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::query()->with(['topic:id,name_en,emoji,needs_approval', 'member:id,display_name,role', 'user:id,name'])->latest('id');

        if (($status = $request->query('status', 'pending')) !== 'all') {
            $query->where('status', $status);
        }

        return response()->json([
            'data' => $query->limit(200)->get()->map(fn (Announcement $a) => $this->payload($a)),
            'counts' => Announcement::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'topics' => AnnouncementTopic::query()->orderBy('sort')->get(),
        ]);
    }

    /** An admin posts directly: translated, published, sent. */
    public function store(Request $request, Translator $translator, AnnouncementPublisher $publisher): JsonResponse
    {
        $data = $request->validate([
            'topic_id' => ['required', Rule::exists('announcement_topics', 'id')],
            'text' => ['required', 'string', 'min:10', 'max:2000'],
            'event_date' => ['nullable', 'date', 'after_or_equal:today'],
            'event_place' => ['nullable', 'string', 'max:160'],
        ]);

        $t = $translator->toAll($data['text']);
        $a = Announcement::query()->create([
            'topic_id' => $data['topic_id'],
            'user_id' => $request->user()->id,
            'original_language' => 'en',
            'text_am' => $t['am'], 'text_ti' => $t['ti'], 'text_en' => $t['en'],
            'event_date' => $data['event_date'] ?? null,
            'event_place' => $data['event_place'] ?? null,
            'status' => 'pending',
        ]);

        $count = $publisher->publish($a, $request->user()->id);

        return response()->json(['announcement' => $this->payload($a->fresh(['topic', 'member', 'user'])), 'subscribers' => $count, 'translated_by' => $t['by']], 201);
    }

    /** Correct the wording; a published one is updated in the channel too. */
    public function update(Request $request, Announcement $announcement, AnnouncementPublisher $publisher): JsonResponse
    {
        $data = $request->validate([
            'text_am' => ['required', 'string', 'max:2000'],
            'text_ti' => ['required', 'string', 'max:2000'],
            'text_en' => ['required', 'string', 'max:2000'],
            'event_date' => ['nullable', 'date'],
            'event_place' => ['nullable', 'string', 'max:160'],
        ]);

        $before = $announcement->only(array_keys($data));
        $announcement->fill($data)->save();
        $publisher->refresh($announcement->fresh(['topic', 'member']));
        AuditLog::record($request->user()->id, 'announcements.edited', 'announcement', $announcement->id, $before, $data);

        return response()->json(['announcement' => $this->payload($announcement->fresh(['topic', 'member', 'user']))]);
    }

    public function approve(Request $request, Announcement $announcement, AnnouncementPublisher $publisher, TelegramBotService $telegram, BotMessages $messages): JsonResponse
    {
        abort_unless($announcement->status === 'pending', 422, 'Only a pending announcement can be approved.');

        $count = $publisher->publish($announcement, $request->user()->id);

        if ($announcement->member?->chat_id) {
            try {
                $telegram->sendText($announcement->member->chat_id, $messages->text('ann_approved_dm', $announcement->member->language));
            } catch (\Throwable) {
            }
        }

        return response()->json(['announcement' => $this->payload($announcement->fresh(['topic', 'member', 'user'])), 'subscribers' => $count]);
    }

    public function reject(Request $request, Announcement $announcement, TelegramBotService $telegram, BotMessages $messages): JsonResponse
    {
        abort_unless($announcement->status === 'pending', 422, 'Only a pending announcement can be rejected.');
        $data = $request->validate(['note' => ['nullable', 'string', 'max:250']]);

        $announcement->forceFill(['status' => 'rejected', 'review_note' => $data['note'] ?? null, 'reviewed_by' => $request->user()->id])->save();
        AuditLog::record($request->user()->id, 'announcements.rejected', 'announcement', $announcement->id, null, $data);

        if ($announcement->member?->chat_id) {
            try {
                $telegram->sendText($announcement->member->chat_id, $messages->text('ann_rejected_dm', $announcement->member->language, ['note' => e($data['note'] ?? '')]));
            } catch (\Throwable) {
            }
        }

        return response()->json(['announcement' => $this->payload($announcement->fresh(['topic', 'member', 'user']))]);
    }

    public function destroy(Request $request, Announcement $announcement, AnnouncementPublisher $publisher): JsonResponse
    {
        $publisher->delete($announcement, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    // ---- topics and leader grants ---------------------------------------------

    public function storeTopic(Request $request): JsonResponse
    {
        $data = $this->topicData($request);
        $base = Str::slug($data['name_en'], '_') ?: 'topic';
        $key = $base;
        $n = 2;
        while (AnnouncementTopic::query()->where('key', $key)->exists()) {
            $key = $base.'_'.$n++;
        }

        $topic = AnnouncementTopic::query()->create($data + ['key' => $key]);
        AuditLog::record($request->user()->id, 'announcements.topic_created', 'announcement_topic', $topic->id, null, $data);

        return response()->json(['topic' => $topic], 201);
    }

    public function updateTopic(Request $request, AnnouncementTopic $topic): JsonResponse
    {
        $data = $this->topicData($request);
        $before = $topic->only(array_keys($data));
        $topic->fill($data)->save();
        AuditLog::record($request->user()->id, 'announcements.topic_updated', 'announcement_topic', $topic->id, $before, $data);

        return response()->json(['topic' => $topic]);
    }

    /** Which topics a leader may post in. */
    public function leaderTopics(Request $request, \App\Models\Member $member): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json(['topic_ids' => DB::table('leader_topics')->where('member_id', $member->id)->pluck('topic_id')]);
        }

        $data = $request->validate(['topic_ids' => ['present', 'array'], 'topic_ids.*' => ['integer', Rule::exists('announcement_topics', 'id')]]);

        DB::transaction(function () use ($member, $data): void {
            DB::table('leader_topics')->where('member_id', $member->id)->delete();
            foreach (array_unique($data['topic_ids']) as $topicId) {
                DB::table('leader_topics')->insert(['member_id' => $member->id, 'topic_id' => $topicId]);
            }
        });

        AuditLog::record($request->user()->id, 'members.leader_topics', 'member', $member->id, null, ['topic_ids' => $data['topic_ids']]);

        return response()->json(['topic_ids' => DB::table('leader_topics')->where('member_id', $member->id)->pluck('topic_id')]);
    }

    private function topicData(Request $request): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:80'],
            'name_am' => ['required', 'string', 'max:80'],
            'name_ti' => ['required', 'string', 'max:80'],
            'emoji' => ['required', 'string', 'max:8'],
            'needs_approval' => ['boolean'],
            'default_on' => ['boolean'],
            'pin' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['boolean'],
        ]);
    }

    private function payload(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'topic' => $a->topic ? ['id' => $a->topic->id, 'name' => $a->topic->name_en, 'emoji' => $a->topic->emoji, 'needs_approval' => $a->topic->needs_approval] : null,
            'poster' => $a->member ? ['id' => $a->member->id, 'name' => $a->member->display_name, 'role' => $a->member->role] : ($a->user ? ['id' => null, 'name' => $a->user->name.' (admin)', 'role' => 'admin'] : null),
            'original_language' => $a->original_language,
            'text_am' => $a->text_am,
            'text_ti' => $a->text_ti,
            'text_en' => $a->text_en,
            'has_photo' => (bool) $a->photo_file_id,
            'event_date' => $a->event_date?->toDateString(),
            'event_place' => $a->event_place,
            'status' => $a->status,
            'review_note' => $a->review_note,
            'on_channel' => (bool) $a->channel_message_id,
            'pinned_until' => $a->pinned_until?->toIso8601String(),
            'dm_count' => $a->dm_count,
            'published_at' => $a->published_at?->toIso8601String(),
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
