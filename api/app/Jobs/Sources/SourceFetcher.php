<?php

namespace App\Jobs\Sources;

use App\Models\JobSource;

interface SourceFetcher
{
    /**
     * @return array<int, RawListing>
     *
     * @throws \RuntimeException when the source cannot be read; the message is shown to an admin
     */
    public function fetch(JobSource $source): array;

    /** Validation rules for this kind's `config`, keyed with a `config.` prefix. */
    public static function configRules(): array;
}
