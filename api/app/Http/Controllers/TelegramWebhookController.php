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

        $chatId = (int) data_get($payload, 'message.chat.id', data_get($payload, 'callback_query.message.chat.id', 0));

        HandleTelegramUpdate::dispatch($update->id, $chatId);

        return response('OK', 200);
    }
}
