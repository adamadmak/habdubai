<?php

namespace App\Jobs\Pipeline;

/** The classifier could not answer now (network, rate limit, overload). Worth retrying. */
class ClassifierUnavailable extends \RuntimeException {}
