<?php

namespace App\Bot;

use App\Ai\AiDeclined;
use App\Ai\AiUnavailable;
use App\Ai\AssistantAnswer;
use App\Ai\ClaudeAssistant;
use App\Ai\ClaudeJson;
use App\Ai\KnowledgeSearch;
use App\Models\AssistantMessage;
use App\Models\Member;
use App\Services\TelegramBotService;
use App\Support\Community;
use Illuminate\Support\Facades\Cache;

/**
 * Anything a member types to the bot outside a flow is a question for the
 * assistant. It answers from checked material, always says where to go when
 * it cannot, and offers to post the question to the community.
 */
class AssistantFlow
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
        private readonly ClaudeAssistant $claude,
        private readonly KnowledgeSearch $search,
    ) {}

    public function ask(Member $member, string $text): void
    {
        $l = $member->language;
        $question = trim($text);

        if ($question === '') {
            return;
        }

        $limit = Community::assistantPerDay();
        $key = 'assistant:'.$member->id.':'.now()->toDateString();
        $used = (int) Cache::get($key, 0);

        if ($used >= $limit) {
            $this->telegram->sendText($member->chat_id, $this->messages->text('ai_limit', $l, ['limit' => $limit]), $this->askGroupKeyboard($l));

            return;
        }

        Cache::put($key, $used + 1, now()->endOfDay());
        $this->telegram->typing($member->chat_id);

        $history = AssistantMessage::query()->where('member_id', $member->id)->orderBy('id')->get(['role', 'text'])
            ->map(fn (AssistantMessage $m) => ['role' => $m->role, 'text' => $m->text])->all();

        $answer = null;
        if (ClaudeJson::configured()) {
            try {
                $answer = $this->claude->answer($question, $l, $history);
            } catch (AiUnavailable|AiDeclined) {
                $answer = null;
            }
        }

        $answer ??= $this->search->answer($question, $l);

        AssistantMessage::remember($member->id, 'user', $question);

        if ($answer === null || trim($answer->text) === '') {
            $fallback = $this->messages->text(ClaudeJson::configured() ? 'ai_unavailable' : 'ai_no_match', $l);
            AssistantMessage::remember($member->id, 'assistant', $fallback);
            $this->telegram->sendText($member->chat_id, $fallback, $this->askGroupKeyboard($l));

            return;
        }

        AssistantMessage::remember($member->id, 'assistant', $answer->text);
        $this->telegram->sendText($member->chat_id, $this->render($answer, $l), $answer->askCommunity ? $this->askGroupKeyboard($l) : null);
    }

    public function render(AssistantAnswer $answer, string $l): string
    {
        $lines = [e($answer->text)];

        if ($answer->by === 'faq') {
            array_unshift($lines, $this->messages->text('ai_faq_matches', $l), '');
        }

        if ($answer->sources !== []) {
            $lines[] = '';
            $lines[] = $this->messages->text('ai_sources', $l);
            foreach ($answer->sources as $source) {
                $lines[] = '• '.($source['url'] ? '<a href="'.e($source['url']).'">'.e($source['title']).'</a>' : e($source['title']));
            }
        }

        $lines[] = '';
        $lines[] = '<i>'.$this->messages->text('ai_footer', $l).'</i>';

        return \App\Support\TelegramText::clip(implode("\n", $lines), 4000);
    }

    private function askGroupKeyboard(string $l): array
    {
        return ['inline_keyboard' => [[['text' => $this->messages->button('btn_ai_ask_group', $l), 'callback_data' => 'q:fromai']]]];
    }

    /** The member's last question to the assistant, for "Ask the community". */
    public static function lastQuestion(Member $member): ?string
    {
        return AssistantMessage::query()->where('member_id', $member->id)->where('role', 'user')->latest('id')->value('text');
    }
}
