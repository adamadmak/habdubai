<?php

namespace App\Bot;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\EmployerReport;
use App\Models\Member;
use App\Services\TelegramBotService;
use App\Support\Community;

/**
 * /safe: emergency numbers, a short "know your rights" card, and a private
 * way to report an employer to moderators. Nothing from the report is ever
 * posted, and the bot says so before asking anything.
 */
class SafetyFlow
{
    public const STATES = ['safe_employer', 'safe_what', 'safe_contact'];

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
    ) {}

    public function card(Member $member): void
    {
        $l = $member->language;

        $extra = array_filter([
            Community::consulatePhone() !== '' ? $this->messages->text('safe_line_consulate', $l, ['phone' => e(Community::consulatePhone())]) : null,
            Community::mohrePhone() !== '' ? $this->messages->text('safe_line_mohre', $l, ['phone' => e(Community::mohrePhone())]) : null,
        ]);

        $this->telegram->sendText(
            $member->chat_id,
            $this->messages->text('safe_card', $l, ['extra' => $extra === [] ? '' : implode("\n", $extra)."\n"]),
            ['inline_keyboard' => [[['text' => $this->messages->button('btn_safe_report', $l), 'callback_data' => 's:report']]]]
        );
    }

    public function beginReport(Member $member, Conversation $conversation): void
    {
        $conversation->forceFill(['state' => 'safe_employer', 'data' => []])->save();
        $this->telegram->sendText($member->chat_id, $this->messages->text('safe_ask_employer', $member->language),
            $this->telegram->replyKeyboard([[$this->messages->button('btn_cancel', $member->language)]], oneTime: false));
    }

    public function handle(Member $member, Conversation $conversation, Incoming $in): void
    {
        $l = $member->language;
        $text = trim((string) $in->text);

        switch ($conversation->state) {
            case 'safe_employer':
                if (mb_strlen($text) < 2 || mb_strlen($text) > 160) {
                    $this->say($member, 'safe_too_short');

                    return;
                }
                $conversation->moveTo('safe_what', ['employer' => $text]);
                $this->say($member, 'safe_ask_what');

                return;

            case 'safe_what':
                if (mb_strlen($text) < 10) {
                    $this->say($member, 'safe_too_short');

                    return;
                }
                $conversation->moveTo('safe_contact', ['text' => mb_substr($text, 0, 5000)]);
                $this->telegram->sendText($member->chat_id, $this->messages->text('safe_ask_contact', $l), $this->telegram->replyKeyboard([
                    [$this->messages->button('btn_yes', $l), $this->messages->button('btn_no', $l)],
                    [$this->messages->button('btn_cancel', $l)],
                ]));

                return;

            case 'safe_contact':
                if ($this->messages->pressed('btn_yes', $text)) {
                    $contactable = true;
                } elseif ($this->messages->pressed('btn_no', $text)) {
                    $contactable = false;
                } else {
                    return;
                }

                $data = $conversation->data ?? [];
                $report = EmployerReport::query()->create([
                    'member_id' => $member->id,
                    'employer_name' => (string) ($data['employer'] ?? ''),
                    'text' => (string) ($data['text'] ?? ''),
                    'contactable' => $contactable,
                    'status' => 'open',
                ]);
                AuditLog::record(null, 'safety.employer_reported', 'employer_report', $report->id);
                $conversation->reset();
                $this->say($member, 'safe_thanks', [], $this->telegram->removeKeyboard());

                return;
        }
    }

    private function say(Member $member, string $key, array $vars = [], ?array $markup = null): void
    {
        $this->telegram->sendText($member->chat_id, $this->messages->text($key, $member->language, $vars), $markup);
    }
}
