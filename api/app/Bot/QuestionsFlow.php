<?php

namespace App\Bot;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Member;
use App\Models\Question;
use App\Models\QuestionReply;
use App\Services\TelegramBotService;
use App\Support\Community;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Questions are asked in the bot and answered in the questions group.
 *
 * /ask takes the question, a category and whether to show the asker's name,
 * then opens a forum topic for it (or posts a plain message when the group
 * has no topics). Every message members send in that topic is recorded as
 * an answer; the first answer from each member earns a point, and the
 * answer the asker (or a moderator) marks as the solution earns five.
 */
class QuestionsFlow
{
    public const STATES = ['ask_text', 'ask_category', 'ask_anonymous'];

    public const POINTS_REPLY = 1;

    public const POINTS_SOLVED = 5;

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
    ) {}

    // ---- asking -------------------------------------------------------------

    public function begin(Member $member, Conversation $conversation, ?string $prefill = null): void
    {
        $l = $member->language;

        if (Community::questionsGroup() === '') {
            $this->say($member, 'ask_no_group');

            return;
        }

        $today = Question::query()->where('member_id', $member->id)->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= Community::questionsPerDay()) {
            $this->say($member, 'ask_limit', ['count' => $today]);

            return;
        }

        if ($prefill !== null && $this->validText($prefill)) {
            $conversation->forceFill(['state' => 'ask_category', 'data' => ['body' => trim($prefill)]])->save();
            $this->askCategory($member);

            return;
        }

        $conversation->forceFill(['state' => 'ask_text', 'data' => []])->save();
        $this->telegram->sendText($member->chat_id, $this->messages->text('ask_question', $l),
            $this->telegram->replyKeyboard([[$this->messages->button('btn_cancel', $l)]], oneTime: false));
    }

    public function handle(Member $member, Conversation $conversation, Incoming $in): void
    {
        $l = $member->language;
        $text = trim((string) $in->text);

        switch ($conversation->state) {
            case 'ask_text':
                if (! $this->validText($text)) {
                    $this->say($member, 'ask_too_short');

                    return;
                }
                $conversation->moveTo('ask_category', ['body' => $text]);
                $this->askCategory($member);

                return;

            case 'ask_category':
                $category = Choices::keyFromLabel(Choices::QUESTION_CATEGORIES, $text);
                if (! $category) {
                    $this->askCategory($member);

                    return;
                }
                $conversation->moveTo('ask_anonymous', ['category' => $category]);
                $this->askAnonymous($member);

                return;

            case 'ask_anonymous':
                if ($this->messages->pressed('btn_ask_named', $text)) {
                    $anonymous = false;
                } elseif ($this->messages->pressed('btn_ask_anonymous', $text)) {
                    $anonymous = true;
                } else {
                    // Anything else (typed words, a sticker): ask again, with the buttons.
                    $this->askAnonymous($member);

                    return;
                }
                $data = $conversation->data ?? [];
                $conversation->reset();
                $this->post($member, (string) $data['body'], (string) $data['category'], $anonymous);

                return;
        }
    }

    private function post(Member $member, string $body, string $category, bool $anonymous): void
    {
        $group = Community::questionsGroup();
        $question = Question::query()->create([
            'member_id' => $member->id,
            'anonymous' => $anonymous,
            'category' => $category,
            'title' => self::title($body),
            'body' => $body,
            'group_chat_id' => (int) $group,
            'status' => 'open',
            'last_activity_at' => now(),
        ]);

        $topicId = $this->telegram->createForumTopic($group, '❓ '.$question->title);

        try {
            $message = $this->telegram->sendToGroup($group, $this->groupText($question), $topicId, $this->solveKeyboard($question));
        } catch (\Throwable $e) {
            $question->delete();
            $this->say($member, 'ask_no_group', [], $this->messages->menuKeyboard($member->language));

            return;
        }

        $question->forceFill(['forum_topic_id' => $topicId, 'group_message_id' => $message['message_id'] ?? null])->save();
        AuditLog::record(null, 'questions.asked', 'question', $question->id, null, ['member_id' => $member->id, 'anonymous' => $anonymous]);

        $this->say($member, 'ask_posted', [], $this->messages->menuKeyboard($member->language));
    }

    // ---- answers in the group ----------------------------------------------

    /** A member's message in the questions group: record it when it answers a question. */
    public function groupMessage(array $message, ?Member $member): void
    {
        if (! $member || ! $member->isActive()) {
            return;
        }

        $question = $this->questionFor($message);
        if (! $question || $question->status === 'closed') {
            return;
        }

        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        if ($text === '' || str_starts_with($text, '/')) {
            return;
        }

        $isAsker = $question->member_id === $member->id;
        $firstFromMember = ! $isAsker && ! QuestionReply::query()->where('question_id', $question->id)->where('member_id', $member->id)->exists();

        try {
            QuestionReply::query()->create([
                'question_id' => $question->id,
                'member_id' => $member->id,
                'telegram_message_id' => (int) $message['message_id'],
                'text' => mb_substr($text, 0, 4000),
                'points_awarded' => $firstFromMember ? self::POINTS_REPLY : 0,
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $question->forceFill(['last_activity_at' => now()])->save();

        if ($firstFromMember) {
            $member->increment('helper_points', self::POINTS_REPLY);

            $asker = $question->member;
            if ($asker?->chat_id && $asker->isActive()) {
                $excerpt = mb_strlen($text) > 300 ? mb_substr($text, 0, 297).'…' : $text;
                try {
                    $this->telegram->sendText($asker->chat_id, $this->messages->text('q_new_answer', $asker->language, [
                        'title' => e($question->title), 'name' => e($member->display_name), 'excerpt' => e($excerpt),
                    ]), ['inline_keyboard' => [[['text' => $this->messages->button('btn_q_solve', $asker->language), 'callback_data' => 'q:solve:'.$question->id]]]]);
                } catch (\Throwable) {
                    // The answer is recorded; the notice is a courtesy.
                }
            }
        }
    }

    private function questionFor(array $message): ?Question
    {
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $threadId = $message['message_thread_id'] ?? null;

        if ($threadId !== null && ! empty($message['is_topic_message'])) {
            return Question::query()->where('group_chat_id', $chatId)->where('forum_topic_id', (int) $threadId)->first();
        }

        $replyTo = $message['reply_to_message']['message_id'] ?? null;
        if ($replyTo !== null) {
            return Question::query()->where('group_chat_id', $chatId)->where('group_message_id', (int) $replyTo)->first();
        }

        return null;
    }

    // ---- marking solved ------------------------------------------------------

    /** "✅ Mark solved" was pressed, under the question in the group or in a DM. */
    public function solveButton(Member $member, Callback $cb, int $questionId): void
    {
        $l = $member->language;
        $question = Question::query()->with('replies.member')->find($questionId);

        if (! $question) {
            $this->telegram->answerCallback($cb->id);

            return;
        }

        if (! $question->canBeSolvedBy($member)) {
            $this->telegram->answerCallback($cb->id, $this->messages->text('q_not_allowed', $l), alert: true);

            return;
        }

        if ($question->status === 'solved') {
            $this->telegram->answerCallback($cb->id, $this->messages->text('q_already_solved', $l), alert: true);

            return;
        }

        $answers = $question->replies->filter(fn (QuestionReply $r) => $r->member_id !== $question->member_id)->take(10);

        if ($answers->isEmpty()) {
            $this->telegram->answerCallback($cb->id, $this->messages->text('q_no_answers', $l), alert: true);

            return;
        }

        $rows = $answers->values()->map(function (QuestionReply $r, int $i) {
            $label = ($i + 1).'. '.($r->member?->display_name ?? '?').': '.preg_replace('/\s+/u', ' ', $r->text);

            return [['text' => mb_strlen($label) > 60 ? mb_substr($label, 0, 57).'…' : $label, 'callback_data' => 'q:pick:'.$r->id]];
        })->all();

        $this->telegram->sendText($member->chat_id, $this->messages->text('q_pick_answer', $l, ['title' => e($question->title)]), ['inline_keyboard' => $rows]);
        $this->telegram->answerCallback($cb->id, $cb->inPrivateChat() ? null : $this->messages->text('q_check_dm', $l), alert: ! $cb->inPrivateChat());
    }

    public function pickButton(Member $member, Callback $cb, int $replyId): void
    {
        $l = $member->language;
        $reply = QuestionReply::query()->with(['question', 'member'])->find($replyId);
        $question = $reply?->question;

        if (! $reply || ! $question || ! $question->canBeSolvedBy($member)) {
            $this->telegram->answerCallback($cb->id, $this->messages->text('q_not_allowed', $l), alert: true);

            return;
        }

        if ($question->status === 'solved') {
            $this->telegram->answerCallback($cb->id, $this->messages->text('q_already_solved', $l), alert: true);

            return;
        }

        $this->markSolved($question, $reply, actorUserId: null);

        if ($cb->messageId) {
            $this->telegram->editText($member->chat_id, $cb->messageId, $this->messages->text('q_solved_dm', $l));
        }
        $this->telegram->answerCallback($cb->id);
    }

    /** Shared with the admin page: mark solved, award points, rename the topic, tell the group. */
    public function markSolved(Question $question, QuestionReply $reply, ?int $actorUserId): void
    {
        $answerer = $reply->member;

        DB::transaction(function () use ($question, $reply, $answerer): void {
            $question->forceFill(['status' => 'solved', 'solved_reply_id' => $reply->id, 'last_activity_at' => now()])->save();

            if ($answerer && $answerer->id !== $question->member_id) {
                $reply->forceFill(['points_awarded' => $reply->points_awarded + self::POINTS_SOLVED])->save();
                $answerer->increment('helper_points', self::POINTS_SOLVED);
            }
        });

        AuditLog::record($actorUserId, 'questions.solved', 'question', $question->id, null, ['reply_id' => $reply->id, 'answerer' => $answerer?->id]);

        $label = Community::labelLanguage();
        $group = (string) $question->group_chat_id;

        if ($question->forum_topic_id) {
            $this->telegram->editForumTopic($group, (int) $question->forum_topic_id, '✅ '.$question->title);
        }
        if ($question->group_message_id) {
            $this->telegram->editText($group, (int) $question->group_message_id, $this->groupText($question), ['inline_keyboard' => []]);
        }

        try {
            $this->telegram->sendToGroup($group, $this->messages->text('q_solved_group', $label, ['name' => e($answerer?->display_name ?? '?')]),
                $question->forum_topic_id ? (int) $question->forum_topic_id : null, null, (int) $reply->telegram_message_id);
        } catch (\Throwable) {
            // The question is solved in our records either way.
        }

        if ($answerer?->chat_id && $answerer->id !== $question->member_id && $answerer->isActive()) {
            try {
                $this->telegram->sendText($answerer->chat_id, $this->messages->text('q_points_dm', $answerer->language, [
                    'title' => e($question->title), 'points' => self::POINTS_SOLVED, 'total' => $answerer->fresh()->helper_points,
                ]));
            } catch (\Throwable) {
            }
        }
    }

    public function myQuestions(Member $member): void
    {
        $l = $member->language;
        $questions = Question::query()->withCount('replies')->where('member_id', $member->id)->latest('id')->limit(10)->get();

        if ($questions->isEmpty()) {
            $this->say($member, 'myq_none');

            return;
        }

        $lines = ['❓ <b>'.e($this->messages->text('myq_title', $l)).'</b>'];
        $rows = [];
        foreach ($questions->values() as $i => $q) {
            $lines[] = '';
            $lines[] = ($i + 1).'. '.($q->status === 'solved' ? '✅ ' : '').'<b>'.e($q->title).'</b>';
            $lines[] = e(Choices::label(Choices::QUESTION_CATEGORIES, $q->category, $l)).' · '.$this->messages->text('myq_answers', $l, ['count' => $q->replies_count]);
            if ($q->status === 'open' && $q->replies_count > 0) {
                $rows[] = [['text' => ($i + 1).'. '.$this->messages->button('btn_q_solve', $l), 'callback_data' => 'q:solve:'.$q->id]];
            }
        }

        $this->telegram->sendText($member->chat_id, implode("\n", $lines), $rows === [] ? null : ['inline_keyboard' => $rows]);
    }

    // ---- helpers ---------------------------------------------------------------

    public function groupText(Question $question): string
    {
        $label = Community::labelLanguage();
        $asker = $question->anonymous
            ? $this->messages->text('q_anonymous_asker', $label)
            : '👤 '.e($question->member?->display_name ?? '').' ✅';

        // A short question is its own title: said twice, it looks like a mistake.
        $sameAsTitle = self::oneLine($question->body) === $question->title;
        $body = match (true) {
            $sameAsTitle => '',
            mb_strlen($question->body) > 3000 => mb_substr($question->body, 0, 2997).'…',
            default => $question->body,
        };

        $post = $this->messages->text('q_group_post', $label, [
            'title' => e($question->title),
            'body' => e($body),
            'asker' => $asker,
            'category' => $question->category,
        ]);

        return ($question->status === 'solved' ? $this->messages->text('q_solved_tag', $label)."\n" : '')
            .($sameAsTitle ? (preg_replace("/\n{3,}/u", "\n\n", $post) ?? $post) : $post);
    }

    private function solveKeyboard(Question $question): array
    {
        return ['inline_keyboard' => [[['text' => $this->messages->button('btn_q_solve', Community::labelLanguage()), 'callback_data' => 'q:solve:'.$question->id]]]];
    }

    public static function title(string $body): string
    {
        $first = self::oneLine($body);

        return mb_strlen($first) > 80 ? rtrim(mb_substr($first, 0, 77)).'…' : $first;
    }

    private static function oneLine(string $body): string
    {
        return trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
    }

    private function validText(string $text): bool
    {
        $length = mb_strlen(trim($text));

        return $length >= 15 && $length <= 2000 && ! str_starts_with(trim($text), '/');
    }

    private function askAnonymous(Member $member): void
    {
        $l = $member->language;
        $this->telegram->sendText($member->chat_id, $this->messages->text('ask_anonymous', $l), $this->telegram->replyKeyboard([
            [$this->messages->button('btn_ask_named', $l), $this->messages->button('btn_ask_anonymous', $l)],
            [$this->messages->button('btn_cancel', $l)],
        ]));
    }

    private function askCategory(Member $member): void
    {
        $l = $member->language;
        $this->telegram->sendText($member->chat_id, $this->messages->text('ask_pick_category', $l),
            $this->telegram->replyKeyboard([...Choices::rows(Choices::QUESTION_CATEGORIES, $l), [$this->messages->button('btn_cancel', $l)]]));
    }

    private function say(Member $member, string $key, array $vars = [], ?array $markup = null): void
    {
        $this->telegram->sendText($member->chat_id, $this->messages->text($key, $member->language, $vars), $markup);
    }
}
