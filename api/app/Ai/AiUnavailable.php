<?php

namespace App\Ai;

/** Claude could not answer now (network, rate limit, overload, bad key). Worth retrying later. */
class AiUnavailable extends \RuntimeException {}
