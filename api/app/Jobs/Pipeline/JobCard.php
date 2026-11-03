<?php

namespace App\Jobs\Pipeline;

use App\Bot\BotMessages;
use App\Bot\Choices;
use App\Models\Job;
use App\Support\JobSettings;

/**
 * How a job looks on Telegram: the channel card, and the short entry used in
 * alerts and lists. Everything a listing author wrote is escaped; only our own
 * markup is HTML.
 */
class JobCard
{
    public function __construct(private readonly BotMessages $messages) {}

    /** The channel card. Labels in the first card language; a summary per card language. */
    public function channelText(Job $job): string
    {
        $languages = JobSettings::cardLanguages();
        $label = $languages[0];

        $lines = [];

        if ($job->status === 'closed') {
            $lines[] = $this->messages->text('job_closed_tag', $label);
        }

        $lines[] = '💼 <b>'.e(Choices::label(Choices::TRADES, $job->category, $label)).'</b> · '
            .e(Choices::label(Choices::JOB_EMIRATES, $job->emirate, $label)).($job->sub_area ? ' · '.e($job->sub_area) : '');
        $lines[] = '<b>'.e($job->title).'</b>';

        if ($job->company) {
            $lines[] = '🏢 '.e($job->company);
        }

        $lines[] = '💰 '.e($job->salaryText() ?? $this->messages->text('job_salary_none', $label));

        $extras = array_filter([
            $job->accommodation === 'yes' ? $this->messages->text('job_accommodation_yes', $label) : null,
            $job->visa === 'yes' ? $this->messages->text('job_visa_yes', $label) : null,
        ]);
        if ($extras !== []) {
            $lines[] = implode('   ', $extras);
        }

        if ($job->languages) {
            $lines[] = '🗣 '.e(implode(', ', $job->languages));
        }

        foreach ($languages as $language) {
            $summary = $job->summary($language);
            if ($summary !== '') {
                $lines[] = '';
                $lines[] = e($summary);
            }
        }

        if ($job->scam_score >= JobSettings::warningThreshold()) {
            $reason = $this->firstReason($job);
            $lines[] = '';
            $lines[] = $this->messages->text('job_warning', $label, ['reason' => e($reason)]);
        }

        $lines[] = '';
        $tags = '#'.$job->category.' #'.$job->emirate;
        $source = $job->source?->name;
        $lines[] = ($source ? e($this->messages->text('job_source', $label, ['source' => $source])).' · ' : '').$tags;

        return mb_substr(implode("\n", $lines), 0, 4000);
    }

    public function channelKeyboard(Job $job): array
    {
        $label = JobSettings::cardLanguages()[0];
        $rows = [];

        if ($job->url && $job->status !== 'closed') {
            $rows[] = [['text' => $this->messages->button('btn_job_open', $label), 'url' => $job->url]];
        }

        if ($job->status !== 'closed') {
            $rows[] = [
                ['text' => $this->messages->button('btn_job_save', $label), 'callback_data' => 'job:save:'.$job->id],
                ['text' => $this->messages->button('btn_job_report', $label), 'callback_data' => 'job:report:'.$job->id],
            ];
        }

        return ['inline_keyboard' => $rows];
    }

    /** A few lines for a list in a member's language: title, where, pay, summary, link. */
    public function entry(Job $job, string $language, int $number): string
    {
        $head = $number.'. <b>'.e($job->title).'</b>'.($job->status === 'closed' ? ' '.$this->messages->text('jobs_closed', $language) : '');
        $meta = e(Choices::label(Choices::JOB_EMIRATES, $job->emirate, $language))
            .' · '.e($job->salaryText() ?? $this->messages->text('job_salary_none', $language))
            .($job->company ? ' · '.e($job->company) : '');

        $summary = $job->summary($language);
        if (mb_strlen($summary) > 220) {
            $summary = mb_substr($summary, 0, 217).'…';
        }

        $lines = [$head, $meta];
        if ($summary !== '') {
            $lines[] = e($summary);
        }
        if ($job->scam_score >= JobSettings::warningThreshold()) {
            $lines[] = $this->messages->text('job_warning', $language, ['reason' => e($this->firstReason($job))]);
        }
        if ($job->url && $job->status !== 'closed') {
            $lines[] = '<a href="'.e($job->url).'">'.e($this->messages->button('btn_job_open', $language)).'</a>';
        }

        return implode("\n", $lines);
    }

    private function firstReason(Job $job): string
    {
        foreach ($job->scam_reasons ?? [] as $reason) {
            return mb_strtolower(preg_replace('/^Model: /', '', (string) $reason) ?? '');
        }

        return 'check this employer before you apply';
    }
}
