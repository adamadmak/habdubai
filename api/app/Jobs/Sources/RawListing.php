<?php

namespace App\Jobs\Sources;

use Carbon\CarbonImmutable;

/** One listing as a source returned it, before anything is decided about it. */
final class RawListing
{
    public function __construct(
        public readonly string $externalKey,
        public readonly string $text,
        public readonly ?string $url = null,
        public readonly ?string $title = null,
        public readonly ?string $company = null,
        public readonly ?string $location = null,
        public readonly ?CarbonImmutable $postedAt = null,
        /** @var array<int, string> */
        public readonly array $photoFileIds = [],
        public readonly ?string $photoUrl = null,
        public readonly ?string $mediaGroup = null,
        public readonly bool $websiteOnly = false,
    ) {}

    public static function clean(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    public function toArray(): array
    {
        return [
            'external_key' => $this->externalKey,
            'url' => $this->url,
            'title' => $this->title,
            'company' => $this->company,
            'location' => $this->location,
            'text' => $this->text,
            'posted_at' => $this->postedAt?->toIso8601String(),
        ];
    }
}
