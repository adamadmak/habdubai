<?php

use App\Models\User;

const SETUP_TOKEN = 'a-setup-token-that-is-at-least-32-chars-long';

test('setup routes do not exist without a token', function (): void {
    putenv('SETUP_TOKEN');

    $this->postJson('/api/setup/migrate')->assertStatus(404);
    $this->withHeader('X-Setup-Token', 'anything')->postJson('/api/setup/admin', ['email' => 'a@b.c', 'password' => 'long-enough-pw'])->assertStatus(404);
});

test('a short or wrong token is refused', function (): void {
    putenv('SETUP_TOKEN=short');
    $this->withHeader('X-Setup-Token', 'short')->postJson('/api/setup/migrate')->assertStatus(404);

    putenv('SETUP_TOKEN='.SETUP_TOKEN);
    $this->withHeader('X-Setup-Token', 'not-it-not-it-not-it-not-it-not-it')->postJson('/api/setup/migrate')->assertStatus(404);
    putenv('SETUP_TOKEN');
});

test('the right token runs migrations and creates the first admin once', function (): void {
    putenv('SETUP_TOKEN='.SETUP_TOKEN);

    $this->withHeader('X-Setup-Token', SETUP_TOKEN)->postJson('/api/setup/migrate')->assertOk()->assertJsonPath('ok', true);

    $this->withHeader('X-Setup-Token', SETUP_TOKEN)->postJson('/api/setup/admin', ['email' => 'Owner@Example.com', 'password' => 'first-admin-password'])
        ->assertStatus(201)->assertJsonPath('user.email', 'owner@example.com');

    expect(User::query()->count())->toBe(1);

    $this->withHeader('X-Setup-Token', SETUP_TOKEN)->postJson('/api/setup/admin', ['email' => 'second@example.com', 'password' => 'another-long-password'])
        ->assertStatus(409);

    $this->postJson('/api/admin/login', ['email' => 'owner@example.com', 'password' => 'first-admin-password'])->assertOk();
    putenv('SETUP_TOKEN');
});
