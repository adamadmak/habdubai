<?php

namespace App\Http\Controllers;

use App\Jobs\HandleTelegramUpdate;
use App\Models\TelegramUpdate;
use App\Services\TelegramBotService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Telegram posts every update here. The controller only checks the secret,
 * records the update and queues it; a handler that is slow or broken never
 * holds Telegram's delivery.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, TelegramBotService $telegram): Response
    {
        // Our fault, not the caller's: a token that can no longer be read says so, and Telegram keeps
        // the update and tries again, rather than being told its own secret is wrong.
        if ($telegram->tokenIsUnreadable()) {
            return response('The bot token cannot be read. Save it again on Settings.', 503);
        }

        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (! $telegram->validWebhookSecret($secret) || ($header !== '' && ! $telegram->validWebhookSecret($header))) {
            return response('Forbidden', 403);
        }

        $payload = $request->json()->all();
        $updateId = (int) ($payload['update_id'] ?? 0);

        if ($updateId <= 0) {
            return self::received();
        }

        try {
            $update = TelegramUpdate::query()->create([
                'update_id' => $updateId,
                'payload' => $payload,
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Telegram redelivered an update we already have.
            return self::received();
        }

        // After the response, in this process: replies stay instant without a
        // queue worker, and Telegram is never kept waiting on a handler. If this
        // process dies first, `bot:updates-chores` runs the update again.
        HandleTelegramUpdate::dispatchAfterResponse($update->id, TelegramUpdate::orderKey($payload));

        return self::received();
    }

    /**
     * With its length, so Telegram has the whole answer at once: this host's PHP server
     * closes the connection only when the handler ends, and without a length Telegram
     * would hold the connection, and the next delivery, until then.
     */
    private static function received(): Response
    {
        return response('OK', 200)->header('Content-Length', '2');
    }
}
