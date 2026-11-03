<?php

namespace App\Jobs\Sources;

use App\Models\JobSource;
use InvalidArgumentException;

final class Fetchers
{
    /** @var array<string, class-string<SourceFetcher>> */
    public const MAP = [
        'rss' => RssFetcher::class,
        'html' => HtmlFetcher::class,
        'telegram' => TelegramSource::class,
    ];

    public static function for(JobSource|string $kind): SourceFetcher
    {
        $kind = $kind instanceof JobSource ? $kind->kind : $kind;
        $class = self::MAP[$kind] ?? throw new InvalidArgumentException('Unknown source kind '.$kind);

        return app($class);
    }

    public static function configRules(string $kind): array
    {
        $class = self::MAP[$kind] ?? null;

        return $class ? $class::configRules() : [];
    }
}
