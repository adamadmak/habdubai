<?php

namespace App\Ai;

/** Claude will not answer this request (refusal, rejected request). Retrying will not help. */
class AiDeclined extends \RuntimeException {}
