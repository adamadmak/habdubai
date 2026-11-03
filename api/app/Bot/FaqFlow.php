<?php

namespace App\Bot;

use App\Ai\KnowledgeSearch;
use App\Models\FaqEntry;
use App\Models\Member;
use App\Services\TelegramBotService;

/** /faq: topics → entries, and "/faq word" to search. */
class FaqFlow
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
        private readonly KnowledgeSearch $search,
    ) {}

    public function menu(Member $member, string $query = '', ?int $editMessageId = null): void
    {
        $l = $member->language;

        if ($query !== '') {
            $entries = $this->search->faq($query, 5);
            if ($entries->isEmpty()) {
                $this->telegram->sendText($member->chat_id, $this->messages->text('faq_search_none', $l, ['query' => e($query)]));

                return;
            }
            $this->telegram->sendText($member->chat_id, $this->entriesText($entries, $l, '🔍 '.e($query)));

            return;
        }

        $counts = FaqEntry::query()->where('active', true)->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category');

        if ($counts->isEmpty()) {
            $this->telegram->sendText($member->chat_id, $this->messages->text('faq_empty', $l));

            return;
        }

        $buttons = [];
        foreach (Choices::QUESTION_CATEGORIES as $key => $labels) {
            if (($counts[$key] ?? 0) > 0) {
                $buttons[] = ['text' => ($labels[$l] ?? $labels['en']).' ('.$counts[$key].')', 'callback_data' => 'f:cat:'.$key];
            }
        }

        $keyboard = ['inline_keyboard' => array_chunk($buttons, 2)];

        if ($editMessageId && $this->telegram->editText($member->chat_id, $editMessageId, $this->messages->text('faq_pick_category', $l), $keyboard)) {
            return;
        }

        $this->telegram->sendText($member->chat_id, $this->messages->text('faq_pick_category', $l), $keyboard);
    }

    public function button(Member $member, Callback $cb): void
    {
        $p = $cb->parts();
        $l = $member->language;

        if (($p[1] ?? '') === 'cat' && array_key_exists($p[2] ?? '', Choices::QUESTION_CATEGORIES)) {
            $entries = FaqEntry::query()->where('active', true)->where('category', $p[2])->orderBy('id')->limit(10)->get();
            $text = $this->entriesText($entries, $l, e(Choices::label(Choices::QUESTION_CATEGORIES, $p[2], $l)));
            $keyboard = ['inline_keyboard' => [[['text' => $this->messages->button('btn_faq_topics', $l), 'callback_data' => 'f:menu']]]];

            if (! $cb->messageId || ! $this->telegram->editText($member->chat_id, $cb->messageId, $text, $keyboard)) {
                $this->telegram->sendText($member->chat_id, $text, $keyboard);
            }
        } else {
            $this->menu($member, '', $cb->messageId);
        }

        $this->telegram->answerCallback($cb->id);
    }

    private function entriesText($entries, string $l, string $title): string
    {
        $lines = ['📚 <b>'.$title.'</b>'];

        foreach ($entries as $entry) {
            $lines[] = '';
            $lines[] = '<b>'.e($entry->q($l)).'</b>';
            $lines[] = e($entry->a($l));
            if ($entry->source_url) {
                $lines[] = '<a href="'.e($entry->source_url).'">'.e($this->messages->text('ai_sources', $l)).' ↗</a>';
            }
        }

        return mb_substr(implode("\n", $lines), 0, 4000);
    }
}
