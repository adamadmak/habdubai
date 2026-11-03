<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RuntimeEnv;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the running API can see, as yes/no only: no value, length or hint of
 * any secret. This host passes environment variables in ways that are easy
 * to get wrong (see CLAUDE.md), and this is the one place that shows it.
 *
 * Open only while the first administrator is being set up. After that it is
 * theirs alone: which secrets are set is nobody else's business, and the
 * admin's Launch page says the same thing in words.
 */
class SystemStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if ($this->hasAdmin()) {
            $user = auth('sanctum')->user();
            abort_unless($user instanceof User && $user->tokenCan('admin'), 404);
        }

        $seen = fn (?string $config, string $env): array => [
            'config' => $config !== null && trim((string) config($config)) !== '',
            'getenv' => (getenv($env) ?: '') !== '',
            'env_file' => RuntimeEnv::fromEnvFile($env) !== '',
            'server' => ! empty($_SERVER[$env] ?? null) || ! empty($_ENV[$env] ?? null),
        ];

        $setting = function (string $key): bool {
            try {
                return trim((string) \App\Models\PlatformSetting::valueFor($key, '')) !== '';
            } catch (\Throwable) {
                return false;
            }
        };

        try {
            $driver = DB::connection()->getDriverName();
            $migrated = Schema::hasTable('owner_login_requests') && Schema::hasColumn('owner_login_requests', 'confirm_code_hash');
        } catch (\Throwable) {
            $driver = 'unreachable';
            $migrated = false;
        }

        return response()->json([
            'config_cached' => app()->configurationIsCached(),
            'env_file_present' => is_readable(base_path('.env')),
            'database' => $driver,
            'migrated' => $migrated,
            'app_key' => $seen('app.key', 'APP_KEY'),
            'setup_token' => $seen('services.host.setup_token', 'SETUP_TOKEN'),
            'tick_token' => $seen('services.host.tick_token', 'TICK_TOKEN') + ['setting' => $setting('tick_token')],
            'site_api_key' => $seen('services.host.site_api_key', 'SITE_API_KEY') + ['setting' => $setting('site_api_key')],
            'db_url' => $seen(null, 'DB_HOST'),
        ])->header('Cache-Control', 'no-store');
    }

    private function hasAdmin(): bool
    {
        try {
            return Schema::hasTable('users') && User::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
