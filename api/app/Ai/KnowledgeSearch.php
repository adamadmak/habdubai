<?php

namespace App\Ai;

use App\Models\FaqEntry;
use App\Models\KnowledgeArticle;
use Illuminate\Support\Collection;

/**
 * Word-overlap search over the FAQ and the knowledge base. It answers when
 * there is no Anthropic key or Claude cannot answer, and powers "/faq word".
 */
class KnowledgeSearch
{
    private const STOP = ['the', 'a', 'an', 'is', 'are', 'to', 'of', 'in', 'on', 'for', 'and', 'or', 'i', 'my', 'me', 'do', 'can', 'how', 'what', 'when', 'where', 'with', 'it', 'be', 'if', 'you', 'your', 'from', 'at', 'does'];

    /** @return Collection<int, FaqEntry> best first */
    public function faq(string $query, int $limit = 3): Collection
    {
        $words = self::words($query);

        if ($words === []) {
            return collect();
        }

        return FaqEntry::query()->where('active', true)->get()
            ->map(fn (FaqEntry $f) => [$f, self::score($words, implode(' ', [$f->q_en, $f->q_am, $f->q_ti, $f->q_om, $f->q_en, $f->a_en]))])
            ->filter(fn ($pair) => $pair[1] > 0)
            ->sortByDesc(fn ($pair) => $pair[1])
            ->take($limit)
            ->map(fn ($pair) => $pair[0])
            ->values();
    }

    /** @return Collection<int, KnowledgeArticle> best first */
    public function articles(string $query, int $limit = 2): Collection
    {
        $words = self::words($query);

        if ($words === []) {
            return collect();
        }

        return KnowledgeArticle::query()->where('active', true)->get()
            ->map(fn (KnowledgeArticle $a) => [$a, self::score($words, $a->title.' '.$a->title.' '.$a->body)])
            ->filter(fn ($pair) => $pair[1] > 0)
            ->sortByDesc(fn ($pair) => $pair[1])
            ->take($limit)
            ->map(fn ($pair) => $pair[0])
            ->values();
    }

    /** The fallback answer: the best FAQ entry, in the member's language. */
    public function answer(string $question, string $language): ?AssistantAnswer
    {
        $faq = $this->faq($question, 1)->first();

        if (! $faq) {
            return null;
        }

        return new AssistantAnswer(
            text: $faq->q($language)."\n\n".$faq->a($language),
            sources: $faq->source_url ? [['title' => 'FAQ', 'url' => $faq->source_url]] : [],
            askCommunity: true,
            by: 'faq',
        );
    }

    /** @return array<int, string> */
    public static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOP, true))));
    }

    /** @param array<int, string> $words */
    private static function score(array $words, string $haystack): int
    {
        $haystack = mb_strtolower($haystack);
        $score = 0;

        foreach ($words as $word) {
            if (str_contains($haystack, $word)) {
                $score++;
            }
        }

        // Most of the words must match, or it is a different question.
        return $score >= max(1, (int) ceil(count($words) / 2)) ? $score : 0;
    }
}
