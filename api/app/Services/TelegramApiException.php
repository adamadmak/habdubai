<?php

namespace App\Services;

use RuntimeException;

/**
 * Telegram answered, and said no. A RuntimeException like every other
 * failure of TelegramBotService, so existing catches keep working, but with
 * Telegram's error code: a 400 (a message too long, a bad button, a chat that
 * does not exist) or a 403 (the member blocked the bot) will be refused again
 * however often it is sent; 429 and 5xx are worth another try.
 */
final class TelegramApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $errorCode = 0, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message, $errorCode);
    }

    /** Sending the same request again cannot succeed. */
    public function isFinal(): bool
    {
        return in_array($this->errorCode, [400, 403], true);
    }

    /** The person blocked the bot, deleted their account, or never started it. */
    public function unreachable(): bool
    {
        return $this->errorCode === 403;
    }
}
