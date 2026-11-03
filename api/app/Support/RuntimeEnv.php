<?php

namespace App\Support;

use App\Models\PlatformSetting;

/**
 * One resolver for every operator-set value.
 *
 *   config key → platform_settings → process env → the .env file on disk
 *
 * The first non-empty value wins. The host builds the config cache at build
 * time without the runtime environment, and a cached config makes Laravel
 * skip .env entirely, so a value set only in the dashboard never reaches
 * config() or env(). The admin's Settings page (platform_settings) is the
 * place that always works.
 */
final class RuntimeEnv
{
    /** @var array<string, string>|null */
    private static ?array $envFile = null;

    public static function get(?string $configKey, ?string $settingKey, ?string $envKey, string $default = ''): string
    {
        $value = $configKey !== null ? trim((string) config($configKey)) : '';

        if ($value === '' && $settingKey !== null) {
            try {
                $value = trim((string) PlatformSetting::valueFor($settingKey, ''));
            } catch (\Throwable) {
                $value = '';
            }
        }

        if ($value === '' && $envKey !== null) {
            $value = trim((string) (getenv($envKey) ?: ''));
        }

        if ($value === '' && $envKey !== null) {
            $value = self::fromEnvFile($envKey);
        }

        return $value !== '' ? $value : $default;
    }

    public static function fromEnvFile(string $key): string
    {
        if (self::$envFile === null) {
            self::$envFile = [];
            $path = base_path('.env');

            if (is_readable($path)) {
                foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim($line);

                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }

                    $split = strpos($line, '=');

                    if ($split === false) {
                        continue;
                    }

                    $name = trim(substr($line, 0, $split));
                    $value = trim(substr($line, $split + 1));

                    $quoted = strlen($value) >= 2
                        && ($value[0] === '"' || $value[0] === "'")
                        && $value[strlen($value) - 1] === $value[0];

                    self::$envFile[$name] = $quoted ? substr($value, 1, -1) : $value;
                }
            }
        }

        return trim((string) (self::$envFile[$key] ?? ''));
    }

    public static function flush(): void
    {
        self::$envFile = null;
    }
}
