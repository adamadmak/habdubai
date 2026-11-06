<?php

namespace App\Console\Commands;

use App\Jobs\Pipeline\JobPublisher;
use App\Models\Job;
use App\Models\JobAlert;
use App\Models\JobPhoto;
use App\Models\JobRaw;
use App\Support\PublicAddress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Closes jobs that are old or whose listing page is gone, and prunes what we
 * promised not to keep: raw listings after thirty days, sent alerts after
 * thirty days.
 *
 * It runs often and does only what is due, because a missed scheduler minute
 * is never run later. A run holds the web worker that took the tick, so it
 * asks about a few links at most, each no more often than every six hours,
 * and stops looking when its time is up; the next run carries on.
 */
class CloseStaleJobs extends Command
{
    private const CHECK_EVERY_HOURS = 6;

    private const LINK_SECONDS = 25;

    protected $signature = 'jobs:close-stale {--check-links=10 : how many open listings to check for a removed page}';

    protected $description = 'Close jobs older than fourteen days or whose listing is gone, and prune old raw rows';

    public function handle(JobPublisher $publisher): int
    {
        $old = Job::query()->where('status', 'published')->where('published_at', '<', now()->subDays(Job::OPEN_DAYS))->limit(50)->get();
        foreach ($old as $job) {
            $publisher->close($job);
        }

        $gone = 0;
        $started = microtime(true);
        $toCheck = Job::query()->where('status', 'published')->whereNotNull('url')
            ->where('url', 'not like', 'https://t.me/%')
            ->where('published_at', '<', now()->subDays(2))
            ->where('updated_at', '<', now()->subHours(self::CHECK_EVERY_HOURS))
            ->orderBy('updated_at')->limit((int) $this->option('check-links'))->get();

        foreach ($toCheck as $job) {
            if (microtime(true) - $started > self::LINK_SECONDS) {
                break;
            }

            // A listing's link came from outside: it is never followed to a private address.
            if (! PublicAddress::allows((string) $job->url)) {
                $job->touch();

                continue;
            }

            try {
                $status = Http::timeout(6)->connectTimeout(3)->withHeaders(['User-Agent' => 'HabUAEBot/1.0'])->head($job->url)->status();
            } catch (\Throwable) {
                $job->touch();

                continue;
            }

            if (in_array($status, [404, 410], true)) {
                $publisher->close($job);
                $gone++;
            } else {
                $job->touch();
            }
        }

        $pruned = JobRaw::query()->where('fetched_at', '<', now()->subDays(30))
            ->whereNotIn('id', Job::query()->whereIn('status', ['published', 'review'])->whereNotNull('raw_id')->select('raw_id'))
            ->delete();
        // Pictures are the heavy part of a job, and nobody needs them a month after it closed.
        JobPhoto::query()->whereIn('job_id', Job::query()->whereIn('status', ['closed', 'rejected', 'duplicate'])
            ->where('updated_at', '<', now()->subDays(30))->select('id'))->delete();
        JobAlert::query()->whereNotNull('sent_at')->where('sent_at', '<', now()->subDays(30))->delete();

        $this->info("Closed {$old->count()} old and {$gone} removed listing(s); pruned {$pruned} raw row(s).");

        return self::SUCCESS;
    }
}
