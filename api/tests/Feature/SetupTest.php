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

test('on a host where our environment never arrives, the database password opens setup', function (): void {
    putenv('SETUP_TOKEN');
    $connection = config('database.default');
    config(["database.connections.{$connection}.password" => 'db-password-from-the-dashboard-123456']);

    $this->withHeader('X-Setup-Token', 'wrong')->postJson('/api/setup/migrate')->assertStatus(404);
    $this->withHeader('X-Setup-Token', 'db-password-from-the-dashboard-123456')->postJson('/api/setup/migrate')
        ->assertOk()->assertJsonPath('driver', 'sqlite');

    // A short database password is no key at all.
    config(["database.connections.{$connection}.password" => 'short']);
    $this->withHeader('X-Setup-Token', 'short')->postJson('/api/setup/migrate')->assertStatus(404);
});

test('the status page says what the app can see, in yes and no only', function (): void {
    $status = $this->getJson('/api/system/status')->assertOk()->json();

    expect($status['database'])->toBe('sqlite')->and($status['migrated'])->toBeTrue()
        ->and($status['tick_token'])->toHaveKeys(['config', 'getenv', 'env_file', 'server', 'setting'])
        ->and(collect($status)->flatten()->every(fn ($v) => is_bool($v) || in_array($v, ['sqlite', 'pgsql', 'mysql'], true)))->toBeTrue();
});
