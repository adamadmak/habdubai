<?php

namespace App\Bot;

use App\Models\Conversation;
use App\Models\Member;
use App\Models\MemberTrade;
use App\Services\MemberRegistrar;
use App\Services\TelegramBotService;
use App\Support\RuntimeEnv;
use Illuminate\Support\Facades\DB;

/**
 * The join flow, one step per state:
 *
 *   language → phone → name → emirate → trades → rules → idle
 *
 * The member row is created at the phone step (that is the moment of
 * verification); the later steps fill it in, and agreeing to the rules is
 * what makes the member active.
 */
class Onboarding
{
    public const STATES = ['language', 'phone', 'name', 'emirate', 'trades', 'rules'];

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
        private readonly MemberRegistrar $registrar,
        private readonly Home $home,
    ) {}

    public function begin(Conversation $conversation, Incoming $in): void
    {
        $conversation->moveTo('language', ['trades' => []]);
        $this->askLanguage($conversation, $in);
    }

    /** Pick up where a chat left off: re-ask the question for its current state. */
    public function resume(Conversation $conversation, Incoming $in): void
    {
        $this->send($conversation, 'resume');

        match ($conversation->state) {
            'language' => $this->askLanguage($conversation, $in),
            'phone' => $this->askPhone($conversation),
            'name' => $this->askName($conversation, $in),
            'emirate' => $this->askEmirate($conversation),
            'trades' => $this->askTrades($conversation),
            'rules' => $this->askRules($conversation),
            default => $this->begin($conversation, $in),
        };
    }

    public function handle(Conversation $conversation, Incoming $in): void
    {
        match ($conversation->state) {
            'language' => $this->onLanguage($conversation, $in),
            'phone' => $this->onPhone($conversation, $in),
            'name' => $this->onName($conversation, $in),
            'emirate' => $this->onEmirate($conversation, $in),
            'trades' => $this->onTrades($conversation, $in),
            'rules' => $this->onRules($conversation, $in),
            default => $this->begin($conversation, $in),
        };
    }

    // ---- language -------------------------------------------------------

    /** The greeting in every language the bot speaks, since none is chosen yet. */
    private function askLanguage(Conversation $conversation, Incoming $in): void
    {
        $this->telegram->sendText(
            $conversation->chat_id,
            implode("\n\n", array_map(fn (string $language) => $this->messages->text('welcome', $language), Choices::LANGUAGES)),
            $this->telegram->replyKeyboard(Choices::languageRows())
        );
    }

    private function onLanguage(Conversation $conversation, Incoming $in): void
    {
        $language = Choices::languageFromLabel((string) $in->text);

        if ($language === null) {
            $this->askLanguage($conversation, $in);

            return;
        }

        $conversation->forceFill(['language' => $language])->save();
        $this->send($conversation, 'language_set');

        $member = Member::findByTelegramUser($in->userId);

        if ($member) {
            // A verified member who left and came back, or who started again:
            // the number is already proven, carry on from the name.
            $member->forceFill(['language' => $language, 'left_at' => null, 'chat_id' => $conversation->chat_id])->save();
            $conversation->moveTo('name');
            $this->askName($conversation, $in);

            return;
        }

        $conversation->moveTo('phone');
        $this->askPhone($conversation);
    }

    // ---- phone ----------------------------------------------------------

    private function askPhone(Conversation $conversation): void
    {
        $this->telegram->sendText(
            $conversation->chat_id,
            $this->messages->text('ask_phone', $conversation->language),
            $this->telegram->contactKeyboard($this->messages->button('btn_share_phone', $conversation->language))
        );
    }

    private function onPhone(Conversation $conversation, Incoming $in): void
    {
        if ($in->contact === null) {
            $this->askPhone($conversation);

            return;
        }

        $result = $this->registrar->verifyContact($in, $conversation->language);

        if (! $result['ok']) {
            $vars = ['support' => $this->supportHandle()];
            $this->telegram->sendText(
                $conversation->chat_id,
                $this->messages->text($result['reason'], $conversation->language, $vars),
                $result['reason'] === 'phone_taken' ? $this->telegram->removeKeyboard() : $this->telegram->contactKeyboard($this->messages->button('btn_share_phone', $conversation->language))
            );

            if ($result['notify'] && $result['notify']->chat_id) {
                try {
                    $this->telegram->notify($result['notify']->chat_id, $this->messages->text('phone_taken_notice', $result['notify']->language));
                } catch (\Throwable) {
                    // The notice is a courtesy; the refusal already stands.
                }
            }

            if ($result['reason'] === 'phone_taken') {
                $conversation->reset();
            }

            return;
        }

        $this->send($conversation, 'phone_ok', [], $this->telegram->removeKeyboard());

        if ($result['outside']) {
            $this->send($conversation, 'phone_country_review');
        }

        $conversation->moveTo('name');
        $this->askName($conversation, $in);
    }

    // ---- name -----------------------------------------------------------

    private function askName(Conversation $conversation, Incoming $in): void
    {
        $suggested = $this->registrar->safeName($in->firstName);
        $conversation->remember('suggested_name', $suggested);

        $this->telegram->sendText(
            $conversation->chat_id,
            $this->messages->text('ask_name', $conversation->language),
            $this->telegram->replyKeyboard([[$this->messages->button('btn_keep_name', $conversation->language, ['name' => $suggested])]])
        );
    }

    private function onName(Conversation $conversation, Incoming $in): void
    {
        $suggested = (string) $conversation->recall('suggested_name', $this->registrar->safeName($in->firstName));
        $text = (string) $in->text;

        if ($this->messages->pressed('btn_keep_name', $text, ['name' => $suggested])) {
            $name = $suggested;
        } elseif ($this->registrar->validName($text)) {
            $name = trim($text);
        } else {
            $this->send($conversation, 'name_invalid');

            return;
        }

        $this->member($in)?->forceFill(['display_name' => $name])->save();
        $conversation->moveTo('emirate');
        $this->askEmirate($conversation);
    }

    // ---- emirate --------------------------------------------------------

    private function askEmirate(Conversation $conversation): void
    {
        $this->telegram->sendText(
            $conversation->chat_id,
            $this->messages->text('ask_emirate', $conversation->language),
            $this->telegram->replyKeyboard(Choices::rows(Choices::EMIRATES, $conversation->language))
        );
    }

    private function onEmirate(Conversation $conversation, Incoming $in): void
    {
        $emirate = Choices::keyFromLabel(Choices::EMIRATES, (string) $in->text);

        if ($emirate === null) {
            $this->askEmirate($conversation);

            return;
        }

        $this->member($in)?->forceFill(['emirate' => $emirate])->save();
        $conversation->moveTo('trades', ['trades' => []]);
        $this->askTrades($conversation);
    }

    // ---- trades ---------------------------------------------------------

    private function askTrades(Conversation $conversation): void
    {
        $rows = Choices::rows(Choices::TRADES, $conversation->language);
        $rows[] = [$this->messages->button('btn_done', $conversation->language)];

        $this->telegram->sendText(
            $conversation->chat_id,
            $this->messages->text('ask_trades', $conversation->language),
            $this->telegram->replyKeyboard($rows, oneTime: false)
        );
    }

    private function onTrades(Conversation $conversation, Incoming $in): void
    {
        $picked = (array) $conversation->recall('trades', []);

        if ($this->messages->pressed('btn_done', $in->text)) {
            if ($picked === []) {
                $this->send($conversation, 'trades_need_one');

                return;
            }

            $member = $this->member($in);

            if ($member) {
                DB::transaction(function () use ($member, $picked): void {
                    MemberTrade::query()->where('member_id', $member->id)->delete();
                    foreach ($picked as $trade) {
                        MemberTrade::query()->create(['member_id' => $member->id, 'trade' => $trade]);
                    }
                });
            }

            $conversation->moveTo('rules');
            $this->askRules($conversation);

            return;
        }

        $trade = Choices::keyFromLabel(Choices::TRADES, (string) $in->text);

        if ($trade === null) {
            $this->askTrades($conversation);

            return;
        }

        if (in_array($trade, $picked, true)) {
            $picked = array_values(array_diff($picked, [$trade]));
        } elseif (count($picked) >= Choices::MAX_TRADES) {
            $this->send($conversation, 'trades_max');

            return;
        } else {
            $picked[] = $trade;
        }

        $conversation->remember('trades', $picked);

        $labels = array_map(fn (string $key) => Choices::label(Choices::TRADES, $key, $conversation->language), $picked);
        $this->send($conversation, $picked === [] ? 'ask_trades' : 'trades_picked', ['trades' => implode(', ', $labels)]);
    }

    // ---- rules ----------------------------------------------------------

    private function askRules(Conversation $conversation): void
    {
        $this->telegram->sendText(
            $conversation->chat_id,
            $this->messages->text('rules', $conversation->language),
            $this->telegram->replyKeyboard([[$this->messages->button('btn_agree', $conversation->language)]])
        );
    }

    private function onRules(Conversation $conversation, Incoming $in): void
    {
        if (! $this->messages->pressed('btn_agree', $in->text)) {
            $this->askRules($conversation);

            return;
        }

        $member = $this->member($in);

        if (! $member) {
            $this->begin($conversation, $in);

            return;
        }

        $member->forceFill(['agreed_rules_at' => now()])->save();
        $conversation->reset();

        // The welcome card, the links as buttons and the menu: App\Bot\Home.
        $this->home->welcome($member->refresh(), $conversation->chat_id);
    }

    // ---- helpers --------------------------------------------------------

    private function supportHandle(): string
    {
        $handle = RuntimeEnv::get(null, 'support_telegram_username', null, '');

        return $handle === '' ? 'the admin' : '@'.ltrim($handle, '@');
    }

    private function member(Incoming $in): ?Member
    {
        return Member::findByTelegramUser($in->userId);
    }

    private function send(Conversation $conversation, string $key, array $vars = [], ?array $markup = null): void
    {
        $this->telegram->sendText($conversation->chat_id, $this->messages->text($key, $conversation->language, $vars), $markup);
    }
}
