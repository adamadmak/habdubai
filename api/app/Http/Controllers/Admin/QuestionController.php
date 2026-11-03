<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Translator;
use App\Bot\Choices;
use App\Bot\QuestionsFlow;
use App\Http\Controllers\Controller;
use App\Support\Search;
use App\Models\AuditLog;
use App\Models\FaqEntry;
use App\Models\Question;
use App\Models\QuestionReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Question::query()->with('member:id,display_name')->withCount('replies')->latest('id');

        if (($status = $request->query('status', 'all')) !== 'all') {
            $query->where('status', $status);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            Search::anyColumn($query, ['body'], $search);
        }

        $page = $query->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Question $q) => $this->payload($q))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'counts' => Question::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'categories' => array_map(fn (array $l) => $l['en'], Choices::QUESTION_CATEGORIES),
        ]);
    }

    public function show(Question $question): JsonResponse
    {
        $question->load(['member:id,display_name,telegram_username', 'replies.member:id,display_name,helper_points']);

        return response()->json([
            'question' => $this->payload($question) + [
                'body' => $question->body,
                'replies' => $question->replies->map(fn (QuestionReply $r) => [
                    'id' => $r->id,
                    'member' => $r->member ? ['id' => $r->member->id, 'name' => $r->member->display_name, 'points' => $r->member->helper_points] : null,
                    'text' => $r->text,
                    'points_awarded' => $r->points_awarded,
                    'is_solution' => $r->id === $question->solved_reply_id,
                    'created_at' => $r->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /** A moderator marks the solving answer from the dashboard. */
    public function solve(Request $request, Question $question, QuestionsFlow $flow): JsonResponse
    {
        $data = $request->validate(['reply_id' => ['required', 'integer']]);
        $reply = QuestionReply::query()->where('question_id', $question->id)->findOrFail($data['reply_id']);
        abort_if($question->status === 'solved', 422, 'This question is already solved.');

        $flow->markSolved($question, $reply->load('member'), $request->user()->id);

        return response()->json(['question' => $this->payload($question->fresh(['member'])->loadCount('replies'))]);
    }

    /** Turn a solved question into an FAQ entry, written in three languages. */
    public function promote(Request $request, Question $question, Translator $translator): JsonResponse
    {
        abort_unless($question->status === 'solved' && $question->solved_reply_id, 422, 'Only a solved question can become an FAQ entry.');
        abort_if($question->faq_id !== null, 422, 'This question is already in the FAQ.');

        $answer = QuestionReply::query()->findOrFail($question->solved_reply_id);
        $written = $translator->faqFrom($question->body, $answer->text);

        $faq = FaqEntry::query()->create([
            'question_id' => $question->id,
            'category' => $question->category,
            'q_am' => $written['q_am'] ?: null,
            'q_ti' => $written['q_ti'] ?: null,
            'q_om' => ($written['q_om'] ?? '') ?: null,
            'q_en' => $written['q_en'],
            'a_am' => $written['a_am'] ?: null,
            'a_ti' => $written['a_ti'] ?: null,
            'a_om' => ($written['a_om'] ?? '') ?: null,
            'a_en' => $written['a_en'],
            // A copied draft waits for an admin to tidy it; a written one goes live.
            'active' => $written['by'] === 'claude',
            'promoted_by' => $request->user()->id,
        ]);

        $question->forceFill(['faq_id' => $faq->id])->save();

        $answerer = $answer->member;
        if ($answerer && $answerer->id !== $question->member_id) {
            $answer->forceFill(['points_awarded' => $answer->points_awarded + 10])->save();
            $answerer->increment('helper_points', 10);
        }

        AuditLog::record($request->user()->id, 'faq.promoted', 'faq', $faq->id, null, ['question_id' => $question->id, 'written_by' => $written['by']]);

        return response()->json(['faq_id' => $faq->id, 'written_by' => $written['by']], 201);
    }

    private function payload(Question $q): array
    {
        return [
            'id' => $q->id,
            'title' => $q->title,
            'category' => $q->category,
            'status' => $q->status,
            'anonymous' => $q->anonymous,
            'asker' => $q->member ? ['id' => $q->member->id, 'name' => $q->member->display_name] : null,
            'replies_count' => $q->replies_count ?? $q->replies()->count(),
            'faq_id' => $q->faq_id,
            'on_forum' => (bool) $q->forum_topic_id,
            'created_at' => $q->created_at?->toIso8601String(),
            'last_activity_at' => $q->last_activity_at?->toIso8601String(),
        ];
    }
}
