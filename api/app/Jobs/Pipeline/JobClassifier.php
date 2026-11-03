<?php

namespace App\Jobs\Pipeline;

use App\Models\JobRaw;

interface JobClassifier
{
    /**
     * @throws ClassifierUnavailable when the classifier cannot answer right now (retry later)
     * @throws ClassifierDeclined when the classifier will not answer for this listing (send to review)
     */
    public function classify(JobRaw $raw): Classification;
}
