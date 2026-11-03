<?php

namespace App\Jobs\Pipeline;

/** The classifier will not answer for this listing (refusal, bad request). Retrying will not help. */
class ClassifierDeclined extends \RuntimeException {}
