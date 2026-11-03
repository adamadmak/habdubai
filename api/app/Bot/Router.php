<?php

namespace App\Bot;

use App\Jobs\Pipeline\JobIngest;
use App\Jobs\Sources\TelegramSource;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\JobSource;
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
        private readonly JobsFlow $jobs,
        private readonly JobIngest $ingest,
        private readonly DirectoryFlow $directory,
        private readonly QuestionsFlow $questions,
        private readonly AssistantFlow $assistant,
        private readonly FaqFlow $faq,
        private readonly SafetyFlow $safety,
        private readonly AnnouncementsFlow $announcements,
        private readonly GroupModerator $moderator,
        private readonly GoodsFlow $goods,
        private readonly \App\Directory\OwnerLogin $ownerLogin,
    ) {}

    public function handle(TelegramUpdate $update): void
    {
        $payload = $update->payload;

        if (isset($payload['callback_query'])) {
            $this->callback(Callback::fromUpdate($payload));

            return;
        }

        if (isset($payload['my_chat_member'])) {
            \App\Models\BotChat::fromMembershipUpdate($payload);

            return;
        }

        // A post in a channel or group registered as a job source.
        $post = $payload['channel_post'] ?? null;
        if ($post === null && isset($payload['message']['chat']['type']) && $payload['message']['chat']['type'] !== 'private') {
            $post = $payload['message'];
        }
        if (is_array($post)) {
            $this->groupPost($post);

            return;
        }

        $in = Incoming::fromUpdate($payload);

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

        // A moderator forwarding a job post from another channel adds it to the pipeline.
        if ($in->isForward() && $member?->isActive() && $member->hasRole('moderator', 'admin')) {
            $this->forwarded($conversation, $in);

            return;
        }

        switch ($in->command()) {
            case 'start':
                // "Continue with Telegram" on the website: anyone may sign in, member or not.
                if (str_starts_with($in->commandArgument(), 'login_')) {
                    $this->ownerLogin->prompt($in->userId, $in->chatId, substr($in->commandArgument(), 6), $language);

                    return;
                }
                if ($in->commandArgument() === 'jobs' && $member?->isActive()) {
                    $this->jobs->menu($member);

                    return;
                }
                // "Interested" under a card in the goods group opens the bot with g<post id>.
                if (preg_match('/^g(\d+)$/', $in->commandArgument(), $m) && $member?->isActive()) {
                    $conversation->reset();
                    $this->goods->interest($member, (int) $m[1]);

                    return;
                }
                $this->start($conversation, $member, $in);

                return;
            case 'jobs':
                $member?->isActive() ? $this->jobs->menu($member) : $this->reply($conversation, 'not_member');

                return;
            case 'saved':
                $member?->isActive() ? $this->jobs->saved($member) : $this->reply($conversation, 'not_member');

                return;
            case 'ask':
                $member?->isActive() ? $this->questions->begin($member, $conversation) : $this->reply($conversation, 'not_member');

                return;
            case 'announce':
                $member?->isActive() ? $this->announcements->begin($member, $conversation) : $this->reply($conversation, 'not_member');

                return;
            case 'topics':
                $member?->isActive() ? $this->announcements->topics($member) : $this->reply($conversation, 'not_member');

                return;
            case 'myquestions':
                $member?->isActive() ? $this->questions->myQuestions($member) : $this->reply($conversation, 'not_member');

                return;
            case 'faq':
                $member?->isActive() ? $this->faq->menu($member, $in->commandArgument()) : $this->reply($conversation, 'not_member');

                return;
            case 'safe':
                // Safety information is for everyone who reaches the bot, member or not.
                if ($member?->isActive()) {
                    $this->safety->card($member);
                } else {
                    $this->reply($conversation, 'safe_card', ['extra' => '']);
                }

                return;
            case 'directory':
            case 'businesses':
                $member?->isActive() ? $this->directory->cities($member) : $this->reply($conversation, 'not_member');

                return;
            case 'addbusiness':
                $member?->isActive() ? $this->directory->beginSuggest($member, $conversation) : $this->reply($conversation, 'not_member');

                return;
            case 'goods':
                $member?->isActive() ? $this->goods->menu($member) : $this->reply($conversation, 'not_member');

                return;
            case 'travel':
            case 'send':
                $member?->isActive()
                    ? $this->goods->begin($member, $conversation, $in->command() === 'travel' ? 'traveller' : 'sender')
                    : $this->reply($conversation, 'not_member');

                return;
            case 'mygoods':
                $member?->isActive() ? $this->goods->mine($member) : $this->reply($conversation, 'not_member');

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

        if (in_array($conversation->state, QuestionsFlow::STATES, true) && $member?->isActive()) {
            $this->questions->handle($member, $conversation, $in);

            return;
        }

        if (in_array($conversation->state, AnnouncementsFlow::STATES, true) && $member?->isActive()) {
            $this->announcements->handle($member, $conversation, $in);

            return;
        }

        if (in_array($conversation->state, SafetyFlow::STATES, true) && $member?->isActive()) {
            $this->safety->handle($member, $conversation, $in);

            return;
        }

        if (in_array($conversation->state, GoodsFlow::STATES, true) && $member?->isActive()) {
            $this->goods->handle($member, $conversation, $in);

            return;
        }

        if (in_array($conversation->state, DirectoryFlow::STATES, true) && $member?->isActive()) {
            $this->directory->handle($member, $conversation, $in);

            return;
        }

        if ($conversation->state === 'dir_search' && $member?->isActive() && $in->text !== null) {
            $this->directory->search($member, $conversation, $in->text);

            return;
        }

        if ($conversation->state === 'jobs_keyword' && $member?->isActive() && $in->text !== null) {
            $this->jobs->keywordEntered($member, $conversation, $in->text);

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

        // Free text from a member, outside any flow, is a question for the assistant.
        if ($member?->isActive() && $conversation->state === 'idle' && $in->text !== null && trim($in->text) !== '') {
            $this->assistant->ask($member, $in->text);

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
            'rank' => ($rank = Choices::rank((int) $member->helper_points)) ? ' · '.$this->messages->text('rank_'.$rank, $language) : '',
            'goods' => $this->goods->profileLine($member),
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

    private function callback(?Callback $cb): void
    {
        if ($cb === null) {
            return;
        }

        $member = Member::findByTelegramUser($cb->userId);
        $parts = $cb->parts();

        if ($member?->isBanned()) {
            $this->telegram->answerCallback($cb->id);

            return;
        }

        if ($parts[0] === 'lg' && in_array($parts[1] ?? '', ['ok', 'no'], true) && $cb->inPrivateChat()) {
            $this->ownerLogin->answer($cb, $parts[1] === 'ok', (int) ($parts[2] ?? 0));

            return;
        }

        if ($parts[0] === 'job' && in_array($parts[1] ?? '', ['save', 'report'], true) && ctype_digit($parts[2] ?? '')) {
            $this->jobs->cardButton($member, $cb, $parts[1], (int) $parts[2]);

            return;
        }

        if ($parts[0] === 'd' && $member?->isActive() && $cb->inPrivateChat()) {
            $conversation = Conversation::forUser($cb->userId, (int) $cb->chatId, $cb->languageCode);
            $this->directory->button($member, $conversation, $cb);

            return;
        }

        if ($parts[0] === 'jf' && $member?->isActive() && $cb->inPrivateChat()) {
            $conversation = Conversation::forUser($cb->userId, (int) $cb->chatId, $cb->languageCode);
            $this->jobs->filterButton($member, $conversation, $cb);

            return;
        }

        // Questions: "Mark solved" works under the question in the group and in DMs.
        if ($parts[0] === 'q' && $member?->isActive()) {
            match ($parts[1] ?? '') {
                'solve' => $this->questions->solveButton($member, $cb, (int) ($parts[2] ?? 0)),
                'pick' => $this->questions->pickButton($member, $cb, (int) ($parts[2] ?? 0)),
                'fromai' => $this->askFromAssistant($member, $cb),
                default => $this->telegram->answerCallback($cb->id),
            };

            return;
        }

        if ($parts[0] === 'an' && ($parts[1] ?? '') === 'del' && $member?->isActive()) {
            $this->announcements->deleteButton($member, $cb, (int) ($parts[2] ?? 0));

            return;
        }

        if ($parts[0] === 'tp' && $member?->isActive() && $cb->inPrivateChat()) {
            $this->announcements->toggleTopic($member, $cb, (int) ($parts[1] ?? 0));

            return;
        }

        if ($parts[0] === 'f' && $member?->isActive() && $cb->inPrivateChat()) {
            $this->faq->button($member, $cb);

            return;
        }

        if ($parts[0] === 'g' && $member?->isActive()) {
            $this->goods->button($member, $cb);

            return;
        }

        if ($parts[0] === 's' && ($parts[1] ?? '') === 'report' && $member?->isActive() && $cb->inPrivateChat()) {
            $this->safety->beginReport($member, Conversation::forUser($cb->userId, (int) $cb->chatId, $cb->languageCode));
            $this->telegram->answerCallback($cb->id);

            return;
        }

        $this->telegram->answerCallback($cb->id);
    }

    private function askFromAssistant(Member $member, Callback $cb): void
    {
        $conversation = Conversation::forUser($cb->userId, (int) $cb->chatId, $cb->languageCode);
        $this->questions->begin($member, $conversation, AssistantFlow::lastQuestion($member));
        $this->telegram->answerCallback($cb->id);
    }

    /** A message in a group or channel: the questions group, or a job source. */
    private function groupPost(array $post): void
    {
        $chatId = (string) ($post['chat']['id'] ?? '');
        \App\Models\BotChat::touchFromMessage($post['chat'] ?? []);

        if (GroupModerator::isCommunityGroup($chatId)) {
            // Moderation first: a removed message is never recorded as an answer.
            if ($this->moderator->check($post)) {
                return;
            }

            if ($chatId === \App\Support\Community::questionsGroup() && isset($post['from']['id'])) {
                $this->questions->groupMessage($post, Member::findByTelegramUser((int) $post['from']['id']));
            }

            return;
        }

        $this->sourcePost($post);
    }

    private function sourcePost(array $post): void
    {
        $chatId = $post['chat']['id'] ?? null;
        $source = $chatId !== null ? JobSource::forTelegramChat($chatId) : null;

        if ($source === null) {
            return;
        }

        $listing = TelegramSource::listing($post, 'tg'.$chatId);

        if ($listing !== null) {
            $this->ingest->storeOne($source, $listing);
            $source->forceFill(['last_run_at' => now(), 'last_success_at' => now(), 'last_found' => 1])->save();
        }
    }

    private function forwarded(Conversation $conversation, Incoming $in): void
    {
        $listing = TelegramSource::listing($in->message, 'fwd');

        if ($listing === null) {
            $this->reply($conversation, 'job_forward_too_short');

            return;
        }

        $stored = $this->ingest->storeOne(JobSource::forwarded(), $listing);
        $this->reply($conversation, $stored ? 'job_forward_added' : 'job_forward_known');
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
