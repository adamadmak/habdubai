<?php

namespace App\Jobs;

use App\Bot\Router;
use App\Models\TelegramUpdate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Runs one stored update through the router. One person's updates run one
 * at a time, waiting up to ten seconds for the previous one, so the steps of
 * a flow are handled in the order they were sent. (A blocking lock, not
 * WithoutOverlapping: this runs after the response in the web process, where
 * a released job would simply be lost.)
 */
class HandleTelegramUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(public readonly int $updateId, public readonly int $chatId) {}

    public function handle(Router $router): void
    {
        $lock = Cache::lock('telegram-chat:'.$this->chatId, 30);

        try {
            $lock->block(10, fn () => $this->run($router));
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            // The previous update is still running; handle this one anyway rather than drop it.
            $this->run($router);
        }
    }

    private function run(Router $router): void
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
