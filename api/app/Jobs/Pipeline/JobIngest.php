<?php

namespace App\Jobs\Pipeline;

use App\Jobs\ProcessJobListing;
use App\Jobs\Sources\RawListing;
use App\Models\JobRaw;
use App\Models\JobSource;
use Illuminate\Database\UniqueConstraintViolationException;

/** Stores what a source returned and queues each new listing for processing. */
class JobIngest
{
    /** @param array<int, RawListing> $listings @return int how many were new */
    public function store(JobSource $source, array $listings): int
    {
        $new = 0;

        foreach ($listings as $listing) {
            $raw = $this->storeOne($source, $listing);

            if ($raw !== null) {
                $new++;
            }
        }

        return $new;
    }

    /** The stored row when the listing is new, null when the source already gave us it. */
    public function storeOne(JobSource $source, RawListing $listing): ?JobRaw
    {
        try {
            $raw = JobRaw::query()->create([
                'source_id' => $source->id,
                'external_key' => $listing->externalKey,
                'url' => $listing->url ? mb_substr($listing->url, 0, 1000) : null,
                'title' => $listing->title,
                'company' => $listing->company,
                'location' => $listing->location,
                'raw_text' => $listing->text,
                'posted_at' => $listing->postedAt,
                'fetched_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        ProcessJobListing::dispatch($raw->id);

        return $raw;
    }
}
