<?php

namespace App\Jobs;

use App\Bot\Router;
use App\Models\TelegramUpdate;
use App\Services\TelegramApiException;
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
        // Longer than a slow Claude call, so a lock never lapses while its holder still runs.
        $lock = Cache::lock('telegram-chat:'.$this->chatId, 120);

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

        if (! $update || $update->processed_at !== null || ! empty($update->payload['redacted'])) {
            return;
        }

        $update->increment('attempts');

        try {
            $router->handle($update);
            // Handled: keep the id and kind only. A contact card carries a phone number.
            $update->forceFill(['processed_at' => now(), 'error' => null, 'payload' => TelegramUpdate::redact($update->payload)])->save();
        } catch (\Throwable $e) {
            // Telegram refused a message for good (too long, a bad button, a blocked bot):
            // running the update again would only repeat whatever happened before the
            // refusal, a post or a request sent twice. It stays failed for the admin to read.
            $final = $e instanceof TelegramApiException && $e->isFinal();

            // A final failure will never be run again, so its content (a contact card's phone
            // number) goes now; processed_at stays null so it still shows as failed.
            $update->forceFill([
                'error' => str($e->getMessage())->limit(500)->toString(),
                'attempts' => $final ? max(TelegramUpdate::MAX_ATTEMPTS, (int) $update->attempts) : (int) $update->attempts,
                'payload' => $final ? TelegramUpdate::redact($update->payload) : $update->payload,
            ])->save();
            Log::error('Telegram update failed.', ['update_id' => $update->update_id, 'error' => $e->getMessage(), 'final' => $final]);

            if (! $final) {
                throw $e;
            }
        }
    }
}
