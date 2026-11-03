<?php

namespace App\Jobs;

use App\Bot\Router;
use App\Models\TelegramUpdate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Runs one stored update through the router. Updates from one chat do not
 * overlap, so a flow's steps are handled in the order they were sent.
 */
class HandleTelegramUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(public readonly int $updateId, public readonly int $chatId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('telegram-chat:'.$this->chatId))->releaseAfter(2)->expireAfter(60)];
    }

    public function handle(Router $router): void
    {
        $update = TelegramUpdate::query()->find($this->updateId);

        if (! $update || $update->processed_at !== null) {
            return;
        }

        try {
            $router->handle($update);
            $update->forceFill(['processed_at' => now(), 'error' => null])->save();
        } catch (\Throwable $e) {
            $update->forceFill(['error' => str($e->getMessage())->limit(500)->toString()])->save();
            Log::error('Telegram update failed.', ['update_id' => $update->update_id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
