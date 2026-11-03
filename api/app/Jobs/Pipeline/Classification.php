<?php

namespace App\Jobs\Pipeline;

/** What the classifier decided about one listing. */
final class Classification
{
    public const CATEGORIES = [
        'domestic', 'driver', 'security', 'cleaning', 'hospitality', 'salon', 'retail',
        'warehouse', 'nursing', 'office', 'construction', 'other',
    ];

    public const EMIRATES = ['dubai', 'sharjah', 'abu_dhabi', 'ajman', 'other_uae', 'outside_uae', 'unknown'];

    public const TRISTATE = ['yes', 'no', 'unknown'];

    /**
     * @param  array<int, string>  $languages
     * @param  array<int, string>  $scamSignals
     */
    public function __construct(
        public readonly bool $isJob,
        public readonly string $category,
        public readonly string $emirate,
        public readonly string $title,
        public readonly ?string $company,
        public readonly ?string $subArea,
        public readonly ?int $salaryMin,
        public readonly ?int $salaryMax,
        public readonly ?string $salaryCurrency,
        public readonly string $accommodation,
        public readonly string $visa,
        public readonly array $languages,
        public readonly string $summaryAm,
        public readonly string $summaryTi,
        public readonly string $summaryEn,
        public readonly int $scamRisk,
        public readonly array $scamSignals,
        public readonly string $by,
        public readonly string $summaryOm = '',
    ) {}

    /** Build from the classifier's JSON, clamping anything out of range rather than trusting it. */
    public static function fromArray(array $data, string $by): self
    {
        $int = static function (mixed $value): ?int {
            if (! is_numeric($value)) {
                return null;
            }
            $value = (int) $value;

            return $value > 0 && $value < 1_000_000 ? $value : null;
        };

        $text = static fn (mixed $value, int $limit): string => mb_substr(trim((string) $value), 0, $limit);
        $nullable = static fn (mixed $value, int $limit): ?string => ($t = mb_substr(trim((string) $value), 0, $limit)) === '' ? null : $t;

        $min = $int($data['salary_min'] ?? null);
        $max = $int($data['salary_max'] ?? null);
        if ($min && $max && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        $currency = strtoupper($text($data['salary_currency'] ?? '', 8));

        return new self(
            isJob: (bool) ($data['is_job'] ?? true),
            category: in_array($data['category'] ?? null, self::CATEGORIES, true) ? $data['category'] : 'other',
            emirate: in_array($data['emirate'] ?? null, self::EMIRATES, true) ? $data['emirate'] : 'unknown',
            title: $text($data['title'] ?? '', 300),
            company: $nullable($data['company'] ?? '', 200),
            subArea: $nullable($data['sub_area'] ?? '', 80),
            salaryMin: $min,
            salaryMax: $max,
            salaryCurrency: ($min || $max) ? ($currency !== '' ? $currency : 'AED') : null,
            accommodation: in_array($data['accommodation'] ?? null, self::TRISTATE, true) ? $data['accommodation'] : 'unknown',
            visa: in_array($data['visa'] ?? null, self::TRISTATE, true) ? $data['visa'] : 'unknown',
            languages: array_slice(array_values(array_filter(array_map(fn ($l) => $text($l, 24), (array) ($data['languages'] ?? [])))), 0, 6),
            summaryAm: $text($data['summary_am'] ?? '', 1200),
            summaryTi: $text($data['summary_ti'] ?? '', 1200),
            summaryEn: $text($data['summary_en'] ?? '', 1200),
            summaryOm: $text($data['summary_om'] ?? '', 1200),
            scamRisk: max(0, min(100, (int) ($data['scam_risk'] ?? 0))),
            scamSignals: array_slice(array_values(array_filter(array_map(fn ($s) => $text($s, 160), (array) ($data['scam_signals'] ?? [])))), 0, 8),
            by: $by,
        );
    }
}
