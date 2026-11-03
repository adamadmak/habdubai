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

    /** Readable names for validation messages ("The item selector field is required."). */
    public const ATTRIBUTES = [
        'config.url' => 'URL',
        'config.item' => 'item selector',
        'config.title' => 'title selector',
        'config.link' => 'link selector',
        'config.company' => 'company selector',
        'config.location' => 'location selector',
        'config.snippet' => 'description selector',
        'config.limit' => 'most items per run',
        'config.chat_id' => 'channel id',
    ];
}
