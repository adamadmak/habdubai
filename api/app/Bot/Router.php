<?php

namespace App\Bot;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Member;
use App\Models\TelegramUpdate;
use App\Services\TelegramBotService;
use App\Support\RuntimeEnv;

/**
 * One update in, the right handler out. Commands first, then whatever flow
 * the chat is in the middle of, then a polite "I did not understand".
 */
class Router
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
        private readonly Onboarding $onboarding,
    ) {}

    public function handle(TelegramUpdate $update): void
    {
        $in = Incoming::fromUpdate($update->payload);

        if ($in === null || ! $in->isPrivate()) {
            return;
        }

        $conversation = Conversation::forUser($in->userId, $in->chatId, $in->languageCode);
        $member = Member::findByTelegramUser($in->userId);

        if ($member && $member->chat_id !== $in->chatId) {
            $member->forceFill(['chat_id' => $in->chatId])->save();
        }

        if ($member?->isBanned()) {
            $this->reply($conversation, 'banned', ['support' => $this->supportHandle()]);

            return;
        }

        $language = $member?->language ?? $conversation->language;

        if ($member && $conversation->language !== $language) {
            $conversation->forceFill(['language' => $language])->save();
        }

        switch ($in->command()) {
            case 'start':
                $this->start($conversation, $member, $in);

                return;
            case 'help':
                $this->reply($conversation, 'help');

                return;
            case 'profile':
                $this->profile($conversation, $member);

                return;
            case 'language':
                $conversation->moveTo('set_language');
                $this->telegram->sendText(
                    $conversation->chat_id,
                    $this->messages->text('choose_language', $language),
                    $this->telegram->replyKeyboard([array_values(Choices::LANGUAGE_LABELS)])
                );

                return;
            case 'leave':
                if (! $member?->isActive()) {
                    $this->reply($conversation, 'not_member');

                    return;
                }
                $conversation->moveTo('confirm_leave');
                $this->telegram->sendText(
                    $conversation->chat_id,
                    $this->messages->text('leave_confirm', $language),
                    $this->telegram->replyKeyboard([[
                        $this->messages->button('btn_confirm_leave', $language),
                        $this->messages->button('btn_cancel', $language),
                    ]])
                );

                return;
            case 'cancel':
                $conversation->reset();
                $this->reply($conversation, 'cancelled', [], $this->telegram->removeKeyboard());

                return;
        }

        if ($this->messages->pressed('btn_cancel', $in->text)) {
            $conversation->reset();
            $this->reply($conversation, 'cancelled', [], $this->telegram->removeKeyboard());

            return;
        }

        if (in_array($conversation->state, Onboarding::STATES, true)) {
            $this->onboarding->handle($conversation, $in);

            return;
        }

        if ($conversation->state === 'set_language') {
            $this->setLanguage($conversation, $member, $in);

            return;
        }

        if ($conversation->state === 'confirm_leave') {
            $this->leave($conversation, $member, $in);

            return;
        }

        $this->reply($conversation, $member ? 'unknown' : 'not_member');
    }

    private function start(Conversation $conversation, ?Member $member, Incoming $in): void
    {
        if ($member?->isActive()) {
            $this->reply($conversation, 'returning', [
                'name' => e($member->display_name),
                'since' => $member->verified_at->format('M Y'),
            ], $this->telegram->removeKeyboard());

            return;
        }

        if (in_array($conversation->state, Onboarding::STATES, true) && $conversation->state !== 'language') {
            $this->onboarding->resume($conversation, $in);

            return;
        }

        $this->onboarding->begin($conversation, $in);
    }

    private function profile(Conversation $conversation, ?Member $member): void
    {
        if (! $member?->isActive()) {
            $this->reply($conversation, 'not_member');

            return;
        }

        $language = $member->language;
        $trades = array_map(fn (string $key) => Choices::label(Choices::TRADES, $key, $language), $member->tradeKeys());

        $this->reply($conversation, 'profile', [
            'name' => e($member->display_name),
            'since' => $member->verified_at->format('M Y'),
            'emirate' => Choices::label(Choices::EMIRATES, $member->emirate, $language),
            'trades' => $trades === [] ? '—' : implode(', ', $trades),
            'language' => Choices::LANGUAGE_LABELS[$language] ?? $language,
            'points' => $member->helper_points,
        ]);
    }

    private function setLanguage(Conversation $conversation, ?Member $member, Incoming $in): void
    {
        $language = Choices::languageFromLabel((string) $in->text);

        if ($language === null) {
            $this->telegram->sendText(
                $conversation->chat_id,
                $this->messages->text('choose_language', $conversation->language),
                $this->telegram->replyKeyboard([array_values(Choices::LANGUAGE_LABELS)])
            );

            return;
        }

        $conversation->forceFill(['language' => $language])->save();
        $member?->forceFill(['language' => $language])->save();
        $conversation->reset();
        $this->reply($conversation, 'language_set', [], $this->telegram->removeKeyboard());
    }

    private function leave(Conversation $conversation, ?Member $member, Incoming $in): void
    {
        if (! $member || ! $this->messages->pressed('btn_confirm_leave', $in->text)) {
            $conversation->reset();
            $this->reply($conversation, 'cancelled', [], $this->telegram->removeKeyboard());

            return;
        }

        $member->forceFill(['left_at' => now()])->save();
        AuditLog::record(null, 'member.left', 'member', $member->id);
        $conversation->reset();

        foreach (['telegram_questions_group_id', 'telegram_goods_group_id'] as $key) {
            $groupId = RuntimeEnv::get(null, $key, null);
            if ($groupId !== '') {
                $this->telegram->kickFromChat($groupId, (int) $member->telegram_user_id);
            }
        }

        $this->reply($conversation, 'left', [], $this->telegram->removeKeyboard());
    }

    private function supportHandle(): string
    {
        $handle = RuntimeEnv::get(null, 'support_telegram_username', null, '');

        return $handle === '' ? 'the admin' : '@'.ltrim($handle, '@');
    }

    private function reply(Conversation $conversation, string $key, array $vars = [], ?array $markup = null): void
    {
        $this->telegram->sendText($conversation->chat_id, $this->messages->text($key, $conversation->language, $vars), $markup);
    }
}
