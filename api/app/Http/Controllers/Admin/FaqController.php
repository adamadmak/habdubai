<?php

namespace App\Http\Controllers\Admin;

use App\Bot\Choices;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FaqEntry;
use App\Models\KnowledgeArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The FAQ and the knowledge base the assistant answers from. */
class FaqController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'faq' => FaqEntry::query()->orderByDesc('active')->latest('id')->get(),
            'knowledge' => KnowledgeArticle::query()->orderByDesc('active')->latest('id')->get(),
            'categories' => array_map(fn (array $l) => $l['en'], Choices::QUESTION_CATEGORIES),
        ]);
    }

    public function storeFaq(Request $request): JsonResponse
    {
        $faq = FaqEntry::query()->create($this->faqData($request) + ['promoted_by' => $request->user()->id]);
        AuditLog::record($request->user()->id, 'faq.created', 'faq', $faq->id);

        return response()->json(['faq' => $faq], 201);
    }

    public function updateFaq(Request $request, FaqEntry $faq): JsonResponse
    {
        $data = $this->faqData($request);
        $faq->fill($data)->save();
        AuditLog::record($request->user()->id, 'faq.updated', 'faq', $faq->id);

        return response()->json(['faq' => $faq]);
    }

    public function destroyFaq(Request $request, FaqEntry $faq): JsonResponse
    {
        $faq->question?->forceFill(['faq_id' => null])->save();
        $faq->delete();
        AuditLog::record($request->user()->id, 'faq.deleted', 'faq', $faq->id);

        return response()->json(['ok' => true]);
    }

    public function storeArticle(Request $request): JsonResponse
    {
        $article = KnowledgeArticle::query()->create($this->articleData($request));
        AuditLog::record($request->user()->id, 'knowledge.created', 'knowledge', $article->id);

        return response()->json(['article' => $article], 201);
    }

    public function updateArticle(Request $request, KnowledgeArticle $article): JsonResponse
    {
        $article->fill($this->articleData($request))->save();
        AuditLog::record($request->user()->id, 'knowledge.updated', 'knowledge', $article->id);

        return response()->json(['article' => $article]);
    }

    public function destroyArticle(Request $request, KnowledgeArticle $article): JsonResponse
    {
        $article->delete();
        AuditLog::record($request->user()->id, 'knowledge.deleted', 'knowledge', $article->id);

        return response()->json(['ok' => true]);
    }

    private function faqData(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(array_keys(Choices::QUESTION_CATEGORIES))],
            'q_en' => ['required', 'string', 'max:500'],
            'a_en' => ['required', 'string', 'max:3000'],
            'q_am' => ['nullable', 'string', 'max:500'],
            'a_am' => ['nullable', 'string', 'max:3000'],
            'q_ti' => ['nullable', 'string', 'max:500'],
            'a_ti' => ['nullable', 'string', 'max:3000'],
            'q_om' => ['nullable', 'string', 'max:500'],
            'a_om' => ['nullable', 'string', 'max:3000'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'active' => ['boolean'],
        ], [], ['q_en' => 'English question', 'a_en' => 'English answer']);
    }

    private function articleData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:20000'],
            'source_name' => ['nullable', 'string', 'max:120'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'active' => ['boolean'],
        ]);
    }
}
