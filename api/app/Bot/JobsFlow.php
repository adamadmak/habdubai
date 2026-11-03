<?php

namespace App\Bot;

use App\Jobs\Pipeline\JobCard;
use App\Jobs\Pipeline\JobPublisher;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Job;
use App\Models\JobFilter;
use App\Models\JobReport;
use App\Models\JobSave;
use App\Models\Member;
use App\Services\TelegramBotService;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Everything a member does with jobs in the bot: the /jobs filter screen
 * (one message edited in place as buttons are pressed), the latest matching
 * jobs, saved jobs, and the Save and Report buttons on channel cards.
 */
class JobsFlow
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
        private readonly JobCard $card,
        private readonly JobPublisher $publisher,
    ) {}

    // ---- commands ---------------------------------------------------------

    public function menu(Member $member, ?int $editMessageId = null): void
    {
        $filter = JobFilter::forMember($member);
        $l = $member->language;

        $text = $this->messages->text('jobs_menu', $l, [
            'categories' => e($this->listLabel(Choices::TRADES, $filter->categories ?? [], $l)),
            'emirates' => e($this->listLabel(Choices::JOB_EMIRATES, $filter->emirates ?? [], $l)),
            'salary' => $filter->salary_min ? 'AED '.number_format($filter->salary_min) : $this->messages->text('jobs_any', $l),
            'keyword' => $filter->keyword ? e($filter->keyword) : $this->messages->text('jobs_none', $l),
            'alerts' => $this->messages->text($filter->alerts_on ? 'jobs_on' : 'jobs_off', $l),
        ]);

        $keyboard = ['inline_keyboard' => [
            [$this->btn('btn_jobs_categories', $l, 'jf:cats'), $this->btn('btn_jobs_emirates', $l, 'jf:ems')],
            [$this->btn('btn_jobs_salary', $l, 'jf:sal'), $this->btn('btn_jobs_keyword', $l, 'jf:kw')],
            [$this->btn($filter->alerts_on ? 'btn_jobs_alerts_off' : 'btn_jobs_alerts_on', $l, 'jf:alerts')],
            [$this->btn('btn_jobs_latest', $l, 'jf:latest'), $this->btn('btn_jobs_saved', $l, 'jf:saved')],
        ]];

        $this->show($member, $text, $keyboard, $editMessageId);
    }

    public function latest(Member $member): void
    {
        $filter = JobFilter::forMember($member);
        $jobs = Job::query()->with('source')->where('status', 'published')->latest('published_at')->limit(200)->get()
            ->filter(fn (Job $job) => $filter->matches($job))
            ->take(5);

        $this->sendList($member, 'jobs_latest_title', $jobs, 'jobs_none_found');
    }

    public function saved(Member $member): void
    {
        $jobs = Job::query()->with('source')
            ->whereIn('id', JobSave::query()->where('member_id', $member->id)->latest('id')->limit(10)->pluck('job_id'))
            ->orderByRaw("case when status = 'published' then 0 else 1 end")->latest('published_at')->get();

        $this->sendList($member, 'jobs_saved_title', $jobs, 'jobs_saved_none');
    }

    /** The keyword the member typed after pressing Keyword. */
    public function keywordEntered(Member $member, Conversation $conversation, string $text): void
    {
        $keyword = trim($text);

        if (mb_strlen($keyword) < 2 || mb_strlen($keyword) > 40 || str_starts_with($keyword, '/')) {
            $this->telegram->sendText($member->chat_id, $this->messages->text('jobs_keyword_invalid', $member->language));

            return;
        }

        JobFilter::forMember($member)->forceFill(['keyword' => $keyword])->save();
        $conversation->reset();
        $this->menu($member);
    }

    // ---- buttons ----------------------------------------------------------

    /** A /jobs screen button (data starts with "jf:"). */
    public function filterButton(Member $member, Conversation $conversation, Callback $cb): void
    {
        $parts = $cb->parts();
        $filter = JobFilter::forMember($member);
        $l = $member->language;
        $messageId = $cb->messageId;

        switch ($parts[1] ?? 'menu') {
            case 'cats':
                $this->toggles($member, 'jobs_pick_categories', Choices::TRADES, $filter->categories ?? [], 'jf:cat:', $messageId);
                break;
            case 'cat':
                $filter->forceFill(['categories' => $this->toggle($filter->categories ?? [], $parts[2] ?? '', array_keys(Choices::TRADES))])->save();
                $this->toggles($member, 'jobs_pick_categories', Choices::TRADES, $filter->categories, 'jf:cat:', $messageId);
                break;
            case 'ems':
                $this->toggles($member, 'jobs_pick_emirates', Choices::JOB_EMIRATES, $filter->emirates ?? [], 'jf:em:', $messageId);
                break;
            case 'em':
                $filter->forceFill(['emirates' => $this->toggle($filter->emirates ?? [], $parts[2] ?? '', array_keys(Choices::JOB_EMIRATES))])->save();
                $this->toggles($member, 'jobs_pick_emirates', Choices::JOB_EMIRATES, $filter->emirates, 'jf:em:', $messageId);
                break;
            case 'sal':
                if (isset($parts[2]) && in_array((int) $parts[2], Choices::SALARY_STEPS, true)) {
                    $filter->forceFill(['salary_min' => (int) $parts[2] ?: null])->save();
                    $this->menu($member, $messageId);
                    break;
                }
                $rows = array_map(fn (int $step) => [[
                    'text' => ($step === (int) $filter->salary_min ? '✅ ' : '').($step === 0 ? $this->messages->text('jobs_any', $l) : 'AED '.number_format($step).'+'),
                    'callback_data' => 'jf:sal:'.$step,
                ]], Choices::SALARY_STEPS);
                $rows[] = [$this->btn('btn_jobs_back', $l, 'jf:menu')];
                $this->show($member, $this->messages->text('jobs_pick_salary', $l), ['inline_keyboard' => $rows], $messageId);
                break;
            case 'kw':
                $conversation->moveTo('jobs_keyword');
                $rows = [];
                if ($filter->keyword) {
                    $rows[] = [$this->btn('btn_jobs_clear_keyword', $l, 'jf:kwclear')];
                }
                $rows[] = [$this->btn('btn_jobs_back', $l, 'jf:menu')];
                $this->show($member, $this->messages->text('jobs_ask_keyword', $l), ['inline_keyboard' => $rows], $messageId);
                break;
            case 'kwclear':
                $filter->forceFill(['keyword' => null])->save();
                $conversation->reset();
                $this->menu($member, $messageId);
                break;
            case 'alerts':
                $filter->forceFill(['alerts_on' => ! $filter->alerts_on])->save();
                $this->menu($member, $messageId);
                break;
            case 'latest':
                $this->latest($member);
                break;
            case 'saved':
                $this->saved($member);
                break;
            default:
                $conversation->state === 'jobs_keyword' && $conversation->reset();
                $this->menu($member, $messageId);
        }

        $this->telegram->answerCallback($cb->id);
    }

    /** Save or Report on a card, in the channel or in a DM. */
    public function cardButton(?Member $member, Callback $cb, string $action, int $jobId): void
    {
        $language = $member?->language ?? (in_array($cb->languageCode, ['am', 'ti', 'om'], true) ? $cb->languageCode : 'en');

        if (! $member?->isActive()) {
            $link = $this->telegram->startLink('jobs');
            $this->telegram->answerCallback($cb->id, $link ? null : $this->messages->text('job_join_first', $language), alert: $link === null, url: $link);

            return;
        }

        $job = Job::query()->find($jobId);

        if (! $job || ! in_array($job->status, ['published', 'review', 'closed'], true)) {
            $this->telegram->answerCallback($cb->id, $this->messages->text('job_gone', $language), alert: true);

            return;
        }

        if ($action === 'save') {
            $existing = JobSave::query()->where('member_id', $member->id)->where('job_id', $job->id)->first();
            if ($existing) {
                $existing->delete();
                $this->telegram->answerCallback($cb->id, $this->messages->text('job_unsaved', $language));
            } else {
                JobSave::query()->create(['member_id' => $member->id, 'job_id' => $job->id, 'created_at' => now()]);
                $this->telegram->answerCallback($cb->id, $this->messages->text('job_saved', $language));
            }

            return;
        }

        $this->report($member, $job, $cb, $language);
    }

    private function report(Member $member, Job $job, Callback $cb, string $language): void
    {
        try {
            JobReport::query()->create(['member_id' => $member->id, 'job_id' => $job->id, 'reason' => 'fake', 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            $this->telegram->answerCallback($cb->id, $this->messages->text('job_already_reported', $language), alert: true);

            return;
        }

        $count = JobReport::query()->where('job_id', $job->id)->count();
        $job->forceFill(['reports_count' => $count])->save();

        if ($count >= JobReport::HIDE_THRESHOLD && $job->status === 'published') {
            $this->publisher->hold($job, $count.' members reported this job as fake.');
            AuditLog::record(null, 'jobs.hidden_by_reports', 'job', $job->id, null, ['reports' => $count]);
        }

        $this->telegram->answerCallback($cb->id, $this->messages->text('job_reported', $language), alert: true);
    }

    // ---- helpers ----------------------------------------------------------

    /** @param array<string, array<string, string>> $list @param array<int, string> $picked */
    private function toggles(Member $member, string $promptKey, array $list, array $picked, string $prefix, ?int $messageId): void
    {
        $l = $member->language;
        $buttons = [];

        foreach ($list as $key => $labels) {
            $buttons[] = ['text' => (in_array($key, $picked, true) ? '✅ ' : '').($labels[$l] ?? $labels['en']), 'callback_data' => $prefix.$key];
        }

        $rows = array_chunk($buttons, 2);
        $rows[] = [$this->btn('btn_jobs_back', $l, 'jf:menu')];

        $this->show($member, $this->messages->text($promptKey, $l), ['inline_keyboard' => $rows], $messageId);
    }

    /** @param array<int, string> $current @param array<int, string> $allowed @return array<int, string> */
    private function toggle(array $current, string $key, array $allowed): array
    {
        if (! in_array($key, $allowed, true)) {
            return $current;
        }

        return in_array($key, $current, true)
            ? array_values(array_diff($current, [$key]))
            : array_values(array_merge($current, [$key]));
    }

    /** @param array<string, array<string, string>> $list @param array<int, string> $picked */
    private function listLabel(array $list, array $picked, string $language): string
    {
        if ($picked === []) {
            return $this->messages->text('jobs_all', $language);
        }

        return implode(', ', array_map(fn (string $key) => Choices::label($list, $key, $language), $picked));
    }

    private function sendList(Member $member, string $titleKey, $jobs, string $emptyKey): void
    {
        $l = $member->language;

        if ($jobs->isEmpty()) {
            $this->telegram->sendText($member->chat_id, $this->messages->text($emptyKey, $l));

            return;
        }

        $body = [$this->messages->text('jobs_list_header', $l, ['title' => e($this->messages->text($titleKey, $l))])];
        $n = 0;
        foreach ($jobs as $job) {
            $body[] = '';
            $body[] = $this->card->entry($job, $l, ++$n);
        }

        $this->telegram->sendText($member->chat_id, mb_substr(implode("\n", $body), 0, 4000));
    }

    private function show(Member $member, string $text, array $keyboard, ?int $editMessageId): void
    {
        if ($editMessageId && $this->telegram->editText($member->chat_id, $editMessageId, $text, $keyboard)) {
            return;
        }

        $this->telegram->sendText($member->chat_id, $text, $keyboard);
    }

    private function btn(string $key, string $language, string $data): array
    {
        return ['text' => $this->messages->button($key, $language), 'callback_data' => $data];
    }
}
