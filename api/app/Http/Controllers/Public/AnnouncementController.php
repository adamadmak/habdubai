<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementTopic;
use App\Support\PublicUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Published announcements and events, in all three languages. */
class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'topic' => ['nullable', 'string', 'max:40'],
            'events' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $topic = isset($data['topic']) ? AnnouncementTopic::query()->where('key', $data['topic'])->first() : null;
        $events = (bool) ($data['events'] ?? false);

        $page = Announcement::query()->with('topic')->where('status', 'published')
            ->when($topic, fn ($q) => $q->where('topic_id', $topic->id))
            ->when($events, fn ($q) => $q->whereNotNull('event_date')->whereDate('event_date', '>=', now('Asia/Dubai')->toDateString())->orderBy('event_date'))
            ->when(! $events, fn ($q) => $q->orderByDesc('published_at')->orderByDesc('id'))
            ->paginate(12);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Announcement $a) => self::payload($a))->values(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'topics' => AnnouncementTopic::query()->where('active', true)->orderBy('sort')->get()->map(fn (AnnouncementTopic $t) => [
                'key' => $t->key, 'emoji' => $t->emoji, 'name' => ['en' => $t->name_en, 'am' => $t->name_am, 'ti' => $t->name_ti],
            ])->values(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    public function show(Announcement $announcement): JsonResponse
    {
        abort_unless($announcement->status === 'published', 404);

        $more = Announcement::query()->with('topic')->where('status', 'published')->whereKeyNot($announcement->id)
            ->where('topic_id', $announcement->topic_id)->orderByDesc('published_at')->limit(3)->get();

        return response()->json([
            'announcement' => self::payload($announcement->loadMissing('topic')),
            'more' => $more->map(fn (Announcement $a) => self::payload($a))->values(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    public static function payload(Announcement $a): array
    {
        $topic = $a->topic;

        return [
            'id' => $a->id,
            'topic' => $topic ? ['key' => $topic->key, 'emoji' => $topic->emoji, 'name' => ['en' => $topic->name_en, 'am' => $topic->name_am, 'ti' => $topic->name_ti]] : null,
            'text' => ['am' => $a->text_am, 'ti' => $a->text_ti, 'en' => $a->text_en],
            'original_language' => $a->original_language,
            'event_date' => $a->event_date?->toDateString(),
            'event_place' => $a->event_place,
            'photo' => $a->photo_file_id ? PublicUrls::api('api/public/announcements/'.$a->id.'/photo') : null,
            'published_at' => $a->published_at?->toIso8601String(),
        ];
    }
}
