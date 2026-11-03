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
        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (! $telegram->validWebhookSecret($secret) || ($header !== '' && ! $telegram->validWebhookSecret($header))) {
            return response('Forbidden', 403);
        }

        $payload = $request->json()->all();
        $updateId = (int) ($payload['update_id'] ?? 0);

        if ($updateId <= 0) {
            return response('OK', 200);
        }

        try {
            $update = TelegramUpdate::query()->create([
                'update_id' => $updateId,
                'payload' => $payload,
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Telegram redelivered an update we already have.
            return response('OK', 200);
        }

        // Ordering key: a person's own updates run one at a time. Button presses
        // key on who pressed (a channel card is pressed by many people at once).
        $orderKey = (int) (data_get($payload, 'callback_query.from.id')
            ?? data_get($payload, 'message.chat.id')
            ?? data_get($payload, 'channel_post.chat.id')
            ?? 0);

        // After the response, in this process: replies stay instant without a
        // queue worker, and Telegram is never kept waiting on a handler.
        HandleTelegramUpdate::dispatchAfterResponse($update->id, $orderKey);

        return response('OK', 200);
    }
}
