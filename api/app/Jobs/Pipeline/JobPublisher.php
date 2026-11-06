<?php

namespace App\Jobs\Pipeline;

use App\Bot\BotMessages;
use App\Models\AuditLog;
use App\Models\Job;
use App\Services\TelegramBotService;
use App\Support\JobSettings;
use Illuminate\Support\Facades\Log;

/**
 * Puts a job on the channel, takes it off, and marks it closed. The database
 * status is the truth; the channel message follows it, and a Telegram error
 * never undoes a decision (it is logged and the card is retried on the next
 * edit).
 */
class JobPublisher
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly JobCard $card,
        private readonly JobAlerts $alerts,
        private readonly BotMessages $messages,
    ) {}

    public function publish(Job $job, ?int $reviewerId = null): void
    {
        $first = $job->published_at === null;

        $job->forceFill([
            'status' => 'published',
            'published_at' => $job->published_at ?? now(),
            'reviewed_by' => $reviewerId ?? $job->reviewed_by,
            'reviewed_at' => $reviewerId ? now() : $job->reviewed_at,
        ])->save();

        $this->post($job);
        $this->alerts->queueFor($job);

        if ($first) {
            $this->tellOwner($job, 'owner_job_live', ['title' => e($job->title), 'link' => $this->jobLink($job)]);
        }
    }

    public function hold(Job $job, string $reason): void
    {
        $this->unpost($job);
        $job->forceFill(['status' => 'review', 'review_reason' => mb_substr($reason, 0, 250)])->save();
    }

    public function reject(Job $job, int $reviewerId, ?string $reason, bool $blockCompany): void
    {
        $this->unpost($job);
        $job->forceFill([
            'status' => 'rejected',
            'review_reason' => $reason ? mb_substr($reason, 0, 250) : $job->review_reason,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        if ($blockCompany && $job->company) {
            JobSettings::blockCompany($job->company);
            AuditLog::record($reviewerId, 'jobs.company_blocked', 'job', $job->id, null, ['company' => $job->company]);
        }

        $this->tellOwner($job, 'owner_job_rejected', ['title' => e($job->title), 'reason' => $reason ? "\n\n".e($reason) : '']);
    }

    public function close(Job $job): void
    {
        $job->forceFill(['status' => 'closed', 'closed_at' => now()])->save();

        $channel = JobSettings::channel();
        if ($channel !== '' && $job->channel_message_id) {
            $this->telegram->editText($channel, (int) $job->channel_message_id, $this->card->channelText($job), $this->card->channelKeyboard($job));
        }
    }

    /** Re-render the card after an edit (approve with changes). */
    public function refresh(Job $job): void
    {
        $channel = JobSettings::channel();
        if ($channel !== '' && $job->channel_message_id) {
            $this->telegram->editText($channel, (int) $job->channel_message_id, $this->card->channelText($job), $this->card->channelKeyboard($job));
        }
    }

    /** The business hears the decision on its own post in the bot. It sees it on the website either way. */
    private function tellOwner(Job $job, string $key, array $vars): void
    {
        $owner = $job->business_id ? $job->business?->owner : null;

        if ($owner === null) {
            return;
        }

        try {
            $this->telegram->sendText($owner->chatId(), $this->messages->text($key, $owner->language, $vars));
        } catch (\Throwable) {
            // Never undoes the decision.
        }
    }

    private function jobLink(Job $job): string
    {
        $url = \App\Support\PublicUrls::site('en/jobs/'.$job->id);

        return $url !== '' ? "\n".$url : '';
    }

    private function post(Job $job): void
    {
        $channel = JobSettings::channel();

        if ($channel === '' || $job->channel_message_id || $job->website_only) {
            return;
        }

        try {
            $message = $this->telegram->sendText($channel, $this->card->channelText($job), $this->card->channelKeyboard($job));
            $job->forceFill(['channel_message_id' => $message['message_id'] ?? null])->save();
        } catch (\Throwable $e) {
            Log::warning('A job could not be posted to the channel.', ['job_id' => $job->id, 'error' => $e->getMessage()]);
        }
    }

    private function unpost(Job $job): void
    {
        $channel = JobSettings::channel();

        if ($channel !== '' && $job->channel_message_id) {
            $this->telegram->deleteMessage($channel, (int) $job->channel_message_id);
        }

        $job->forceFill(['channel_message_id' => null])->save();
    }
}
