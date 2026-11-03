<?php

namespace App\Jobs;

use App\Jobs\Pipeline\Classification;
use App\Jobs\Pipeline\ClassifierDeclined;
use App\Jobs\Pipeline\ClassifierUnavailable;
use App\Jobs\Pipeline\Deduper;
use App\Jobs\Pipeline\JobClassifier;
use App\Jobs\Pipeline\JobPublisher;
use App\Jobs\Pipeline\RulesJobClassifier;
use App\Jobs\Pipeline\ScamScorer;
use App\Models\Job;
use App\Models\JobRaw;
use App\Support\JobSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One raw listing through the pipeline: dedupe, classify, score, then publish
 * or hold for review. A classifier that cannot answer is retried three times
 * with backoff; after that the listing is classified by rules and held for a
 * moderator with the reason, never dropped.
 */
class ProcessJobListing implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Under the queue's retry_after (300 s): a stuck run is stopped before anyone else takes it. */
    public int $timeout = 240;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $rawId) {}

    public function handle(Deduper $deduper, JobClassifier $classifier, ScamScorer $scorer, JobPublisher $publisher): void
    {
        // One run per listing at a time: a second worker that picks up the same listing
        // (a retry while the first still waits on Claude) leaves it alone.
        $lock = Cache::lock('job-raw:'.$this->rawId, $this->timeout + 30);
        if (! $lock->get()) {
            return;
        }

        try {
            $this->process($deduper, $classifier, $scorer, $publisher);
        } finally {
            $lock->release();
        }
    }

    private function process(Deduper $deduper, JobClassifier $classifier, ScamScorer $scorer, JobPublisher $publisher): void
    {
        $raw = JobRaw::query()->with('source')->find($this->rawId);

        // Already done, or a job row already made from it: never twice.
        if (! $raw || $raw->processed_at !== null || Job::query()->where('raw_id', $raw->id)->exists()) {
            return;
        }

        $original = $deduper->duplicateOf($raw);

        if ($original) {
            Job::query()->create($this->baseAttributes($raw) + [
                'title' => mb_substr($raw->title ?: $original->title, 0, 300),
                'status' => 'duplicate',
                'duplicate_of' => $original->id,
            ]);
            $raw->forceFill(['processed_at' => now()])->save();

            return;
        }

        try {
            $classification = $classifier->classify($raw);
            $holdReason = null;
        } catch (ClassifierUnavailable $e) {
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
            $classification = app(RulesJobClassifier::class)->classify($raw);
            $holdReason = 'Not classified: '.$e->getMessage();
        } catch (ClassifierDeclined $e) {
            $classification = app(RulesJobClassifier::class)->classify($raw);
            $holdReason = 'Not classified: '.$e->getMessage();
        }

        $this->finish($raw, $classification, $holdReason, $scorer, $publisher);
    }

    public function finish(JobRaw $raw, Classification $c, ?string $holdReason, ScamScorer $scorer, JobPublisher $publisher): void
    {
        if (! $c->isJob) {
            Job::query()->create($this->baseAttributes($raw) + $this->classified($c, $raw) + [
                'status' => 'rejected',
                'review_reason' => 'Not a job listing (classifier).',
            ]);
            $raw->forceFill(['processed_at' => now()])->save();

            return;
        }

        $scam = $scorer->score($raw, $c);

        $job = Job::query()->create($this->baseAttributes($raw) + $this->classified($c, $raw) + [
            'scam_score' => $scam['score'],
            'scam_reasons' => $scam['reasons'],
            'status' => 'pending',
        ]);

        $raw->forceFill(['processed_at' => now()])->save();

        $reason = $holdReason
            ?? ($scam['score'] >= JobSettings::reviewThreshold() ? 'Scam score '.$scam['score'].': '.($scam['reasons'][0] ?? 'high risk') : null)
            ?? (! JobSettings::autoPublish() ? 'Auto-publish is off.' : null)
            ?? ($c->emirate === 'outside_uae' ? 'The job is outside the UAE.' : null);

        if ($reason !== null) {
            $publisher->hold($job, $reason);

            return;
        }

        $publisher->publish($job);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('A job listing could not be processed.', ['raw_id' => $this->rawId, 'error' => $e->getMessage()]);
    }

    private function baseAttributes(JobRaw $raw): array
    {
        return [
            'raw_id' => $raw->id,
            'source_id' => $raw->source_id,
            'url' => $raw->url,
            'url_key' => Deduper::urlKey($raw->url),
            'fingerprint' => Deduper::fingerprint($raw->title, $raw->company),
        ];
    }

    private function classified(Classification $c, JobRaw $raw): array
    {
        return [
            'category' => $c->category,
            'emirate' => $c->emirate,
            'sub_area' => $c->subArea,
            'title' => $c->title !== '' ? $c->title : mb_substr($raw->title ?? 'Job', 0, 300),
            'company' => $c->company ?? $raw->company,
            'salary_min' => $c->salaryMin,
            'salary_max' => $c->salaryMax,
            'salary_currency' => $c->salaryCurrency,
            'accommodation' => $c->accommodation,
            'visa' => $c->visa,
            'languages' => $c->languages,
            'summary_am' => $c->summaryAm ?: null,
            'summary_ti' => $c->summaryTi ?: null,
            'summary_en' => $c->summaryEn ?: null,
            'classified_by' => $c->by,
        ];
    }
}
