<?php

namespace App\Console\Commands;

use App\Jobs\HandleTelegramUpdate;
use App\Models\TelegramUpdate;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * For development only: Telegram cannot reach a webhook on localhost, so
 * this asks Telegram for updates (long polling) and runs each one through
 * the same path the webhook uses. Refuses while a webhook is registered,
 * because getUpdates and a webhook cannot both be active and deleting the
 * webhook would stop the live bot.
 */
class PollTelegram extends Command
{
    protected $signature = 'bot:poll {--once : fetch one batch and stop}';

    protected $description = 'Development: receive Telegram updates by polling instead of a webhook';

    public function handle(TelegramBotService $telegram): int
    {
        if (! $telegram->configured()) {
            $this->error('No bot token. Paste it on the admin Settings page first.');

            return self::FAILURE;
        }

        $webhook = (string) ($telegram->getWebhookInfo()['url'] ?? '');
        if ($webhook !== '') {
            $this->error('This bot has a webhook registered, so it is live somewhere. Polling would need it removed; use a separate test bot for local work.');

            return self::FAILURE;
        }

        $me = $telegram->getMe();
        $this->info('Polling as @'.($me['username'] ?? '?').'. Write to the bot from Telegram; Ctrl+C to stop.');

        $offset = (int) (TelegramUpdate::query()->max('update_id') ?? 0) + 1;

        do {
            try {
                $updates = $telegram->getUpdates($offset, $this->option('once') ? 0 : 25);
            } catch (\Throwable $e) {
                $this->warn($e->getMessage());
                sleep(3);

                continue;
            }

            foreach ($updates as $payload) {
                $updateId = (int) ($payload['update_id'] ?? 0);
                $offset = max($offset, $updateId + 1);

                try {
                    $update = TelegramUpdate::query()->create(['update_id' => $updateId, 'payload' => $payload, 'received_at' => now()]);
                } catch (UniqueConstraintViolationException) {
                    continue;
                }

                $orderKey = (int) (data_get($payload, 'callback_query.from.id') ?? data_get($payload, 'message.chat.id') ?? data_get($payload, 'channel_post.chat.id') ?? 0);
                $this->line('update '.$updateId.' '.implode(',', array_diff(array_keys($payload), ['update_id'])));

                try {
                    HandleTelegramUpdate::dispatchSync($update->id, $orderKey);
                } catch (\Throwable $e) {
                    $this->warn('  failed: '.$e->getMessage());
                }
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
