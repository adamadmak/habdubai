<?php

namespace App\Jobs\Pipeline;

use App\Bot\BotMessages;
use App\Models\Job;
use App\Models\JobAlert;
use App\Models\JobFilter;
use App\Services\TelegramApiException;
use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Log;

/**
 * Job alerts in two halves. Publishing a job queues a row for every member
 * whose filter matches; a scheduled send then gathers each member's queue
 * into one message, at most once every fifteen minutes, so a burst of new
 * listings never becomes a burst of notifications.
 */
class JobAlerts
{
    public const GAP_MINUTES = 15;

    public const MAX_PER_MESSAGE = 5;

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
        private readonly JobCard $card,
    ) {}

    /** @return int how many members were queued */
    public function queueFor(Job $job): int
    {
        $queued = 0;

        JobFilter::query()
            ->where('alerts_on', true)
            ->whereHas('member', fn ($q) => $q->whereNotNull('agreed_rules_at')->whereNull('banned_at')->whereNull('left_at'))
            ->chunkById(500, function ($filters) use ($job, &$queued): void {
                foreach ($filters as $filter) {
                    if ($filter->matches($job)) {
                        JobAlert::query()->insertOrIgnore(['member_id' => $filter->member_id, 'job_id' => $job->id, 'created_at' => now()]);
                        $queued++;
                    }
                }
            });

        return $queued;
    }

    /** @return int how many messages were sent */
    public function sendDue(): int
    {
        $sent = 0;
        $cutoff = now()->subMinutes(self::GAP_MINUTES);

        $memberIds = JobAlert::query()->whereNull('sent_at')->distinct()->pluck('member_id');

        foreach ($memberIds as $memberId) {
            $filter = JobFilter::query()->with('member')->where('member_id', $memberId)->first();
            $member = $filter?->member;

            if (! $member || ! $member->isActive() || ! $filter->alerts_on || ! $member->chat_id) {
                JobAlert::query()->where('member_id', $memberId)->whereNull('sent_at')->delete();

                continue;
            }

            if ($filter->last_alert_at && $filter->last_alert_at->greaterThan($cutoff)) {
                continue;
            }

            $pending = JobAlert::query()->with('job.source')->where('member_id', $memberId)->whereNull('sent_at')->oldest('id')->get();
            $open = $pending->filter(fn (JobAlert $a) => $a->job?->status === 'published')->take(self::MAX_PER_MESSAGE);

            if ($open->isEmpty()) {
                JobAlert::query()->whereIn('id', $pending->pluck('id'))->update(['sent_at' => now()]);

                continue;
            }

            $language = $member->language;
            $body = [$this->messages->text('jobs_list_header', $language, ['title' => e($this->messages->text('jobs_alert_title', $language))])];
            $n = 0;
            foreach ($open as $alert) {
                $body[] = '';
                $body[] = $this->card->entry($alert->job, $language, ++$n);
            }
            $body[] = '';
            $body[] = $this->messages->text('jobs_alert_footer', $language);

            try {
                $this->telegram->sendText($member->chat_id, \App\Support\TelegramText::clip(implode("\n", $body), 4000));
            } catch (\Throwable $e) {
                Log::warning('A job alert could not be sent.', ['member_id' => $memberId, 'error' => $e->getMessage()]);

                // Blocked the bot or refused for good: these alerts can never arrive. Without this
                // they would be tried again every five minutes and pile up for as long as the block lasts.
                if ($e instanceof TelegramApiException && $e->isFinal()) {
                    JobAlert::query()->whereIn('id', $pending->pluck('id'))->update(['sent_at' => now()]);
                    $filter->forceFill(['last_alert_at' => now()])->save();
                }

                continue;
            }

            // Everything pending is now handled: what was not shown is closed or beyond the five.
            JobAlert::query()->whereIn('id', $pending->pluck('id'))->update(['sent_at' => now()]);
            $filter->forceFill(['last_alert_at' => now()])->save();
            $sent++;
        }

        return $sent;
    }
}
