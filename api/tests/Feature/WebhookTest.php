<?php

use App\Models\TelegramUpdate;

test('refuses a wrong secret', function (): void {
    $this->postJson('/api/telegram/webhook/not-the-secret-at-all', telegramMessage(1, ['text' => '/start']))
        ->assertStatus(403);

    expect(TelegramUpdate::query()->count())->toBe(0);
});

test('refuses a header that does not match even when the path does', function (): void {
    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'abcdefghijklmnopqrstuvwxyz')
        ->postJson('/api/telegram/webhook/'.webhookSecret(), telegramMessage(1, ['text' => '/start']))
        ->assertStatus(403);
});

test('stores an update once and ignores a redelivery', function (): void {
    $update = telegramMessage(42, ['text' => '/help']);

    $this->postJson('/api/telegram/webhook/'.webhookSecret(), $update)->assertOk();
    $this->postJson('/api/telegram/webhook/'.webhookSecret(), $update)->assertOk();

    expect(TelegramUpdate::query()->count())->toBe(1)
        ->and(TelegramUpdate::query()->first()->processed_at)->not->toBeNull()
        ->and(count(sentMessages()))->toBe(1);
});

test('ignores a group message for now', function (): void {
    $update = telegramMessage(42, ['text' => '/start', 'chat' => ['id' => -1001, 'type' => 'supergroup', 'title' => 'Questions']]);

    $this->postJson('/api/telegram/webhook/'.webhookSecret(), $update)->assertOk();

    expect(sentMessages())->toBe([]);
});
