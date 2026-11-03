<?php

namespace App\Console\Commands;

use App\Jobs\FetchJobSource;
use App\Models\JobSource;
use Illuminate\Console\Command;

class FetchDueSources extends Command
{
    protected $signature = 'jobs:fetch-due';

    protected $description = 'Queue a fetch for every job source whose interval has passed';

    public function handle(): int
    {
        $due = JobSource::query()->where('active', true)->whereNull('paused_at')->where('kind', '!=', 'telegram')->get()
            ->filter(fn (JobSource $source) => $source->isDue());

        foreach ($due as $source) {
            // Mark now so a slow queue does not queue the same source twice.
            $source->forceFill(['last_run_at' => now()])->save();
            FetchJobSource::dispatch($source->id);
        }

        $this->info('Queued '.$due->count().' source(s).');

        return self::SUCCESS;
    }
}
