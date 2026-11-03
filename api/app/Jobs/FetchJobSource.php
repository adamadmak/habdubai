<?php

namespace App\Jobs;

use App\Jobs\Pipeline\JobIngest;
use App\Jobs\Sources\Fetchers;
use App\Models\AuditLog;
use App\Models\JobSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * One run of one source. A run that throws or finds nothing counts as a
 * failure; three in a row pause the source so a broken scraper stops
 * hammering a site, and the dashboard shows it.
 */
class FetchJobSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $sourceId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('job-source:'.$this->sourceId))->dontRelease()->expireAfter(600)];
    }

    /** @return array{found: int, new: int, error: ?string} */
    public function handle(JobIngest $ingest): array
    {
        $source = JobSource::query()->find($this->sourceId);

        if (! $source) {
            return ['found' => 0, 'new' => 0, 'error' => 'Source not found.'];
        }

        $source->forceFill(['last_run_at' => now()])->save();

        try {
            $listings = Fetchers::for($source)->fetch($source);
        } catch (\Throwable $e) {
            $this->fail_($source, $e->getMessage());

            return ['found' => 0, 'new' => 0, 'error' => $e->getMessage()];
        }

        $new = $ingest->store($source, $listings);

        $source->forceFill([
            'last_success_at' => now(),
            'last_error' => null,
            'failures_in_row' => 0,
            'last_found' => count($listings),
            'last_new' => $new,
        ])->save();

        return ['found' => count($listings), 'new' => $new, 'error' => null];
    }

    private function fail_(JobSource $source, string $message): void
    {
        $failures = $source->failures_in_row + 1;
        $source->forceFill([
            'last_error' => mb_substr($message, 0, 1000),
            'failures_in_row' => $failures,
            'last_found' => 0,
            'last_new' => 0,
        ])->save();

        if ($failures >= JobSource::MAX_FAILURES && $source->paused_at === null) {
            $source->forceFill(['paused_at' => now(), 'pause_reason' => 'Paused after '.$failures.' failed runs: '.mb_substr($message, 0, 200)])->save();
            AuditLog::record(null, 'jobs.source_paused', 'job_source', $source->id, null, ['error' => mb_substr($message, 0, 500)]);
        }
    }
}
