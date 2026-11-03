<?php

namespace App\Bot;

/**
 * The fixed lists a member picks from. Keys are what the database stores;
 * labels are what the keyboard shows, per language. A pressed button is
 * matched against the labels of every language, so a member who switched
 * language mid-flow is still understood.
 */
final class Choices
{
    public const LANGUAGES = ['am', 'ti', 'en'];

    public const LANGUAGE_LABELS = [
        'am' => 'አማርኛ',
        'ti' => 'ትግርኛ',
        'en' => 'English',
    ];

    public const EMIRATES = [
        'dubai' => ['am' => 'ዱባይ', 'ti' => 'ዱባይ', 'en' => 'Dubai'],
        'sharjah' => ['am' => 'ሻርጃ', 'ti' => 'ሻርጃ', 'en' => 'Sharjah'],
        'abu_dhabi' => ['am' => 'አቡ ዳቢ', 'ti' => 'ኣቡ ዳቢ', 'en' => 'Abu Dhabi'],
        'ajman' => ['am' => 'አጅማን', 'ti' => 'ዓጅማን', 'en' => 'Ajman'],
        'other' => ['am' => 'ሌላ ኢማራት', 'ti' => 'ካልእ ኢማራት', 'en' => 'Other emirate'],
    ];

    public const TRADES = [
        'domestic' => ['am' => 'የቤት ሥራ', 'ti' => 'ናይ ገዛ ስራሕ', 'en' => 'Domestic work'],
        'driver' => ['am' => 'ሹፌር', 'ti' => 'ሹፌር', 'en' => 'Driver'],
        'security' => ['am' => 'ጥበቃ', 'ti' => 'ሓለዋ', 'en' => 'Security'],
        'cleaning' => ['am' => 'ጽዳት', 'ti' => 'ጽሬት', 'en' => 'Cleaning'],
        'hospitality' => ['am' => 'ሆቴልና ሬስቶራንት', 'ti' => 'ሆቴልን ቤት መግብን', 'en' => 'Hospitality'],
        'salon' => ['am' => 'ሳሎንና ውበት', 'ti' => 'ሳሎንን ጽባቐን', 'en' => 'Salon and beauty'],
        'retail' => ['am' => 'ሽያጭ', 'ti' => 'መሸጣ', 'en' => 'Retail and sales'],
        'warehouse' => ['am' => 'መጋዘንና ዴሊቨሪ', 'ti' => 'መኽዘንን ደሊቨሪን', 'en' => 'Warehouse and delivery'],
        'nursing' => ['am' => 'ነርስና እንክብካቤ', 'ti' => 'ነርስን ክንክንን', 'en' => 'Nursing and care'],
        'office' => ['am' => 'ቢሮ', 'ti' => 'ቤት ጽሕፈት', 'en' => 'Office and admin'],
        'construction' => ['am' => 'ግንባታና ቴክኒክ', 'ti' => 'ህንጻን ቴክኒክን', 'en' => 'Construction and technical'],
        'other' => ['am' => 'ሌላ', 'ti' => 'ካልእ', 'en' => 'Other'],
    ];

    public const MAX_TRADES = 3;

    /** Where a job is. Wider than EMIRATES: a listing can be elsewhere in the UAE, abroad, or unstated. */
    public const JOB_EMIRATES = [
        'dubai' => ['am' => 'ዱባይ', 'ti' => 'ዱባይ', 'en' => 'Dubai'],
        'sharjah' => ['am' => 'ሻርጃ', 'ti' => 'ሻርጃ', 'en' => 'Sharjah'],
        'abu_dhabi' => ['am' => 'አቡ ዳቢ', 'ti' => 'ኣቡ ዳቢ', 'en' => 'Abu Dhabi'],
        'ajman' => ['am' => 'አጅማን', 'ti' => 'ዓጅማን', 'en' => 'Ajman'],
        'other_uae' => ['am' => 'ሌላ ኢማራት', 'ti' => 'ካልእ ኢማራት', 'en' => 'Other emirate'],
        'outside_uae' => ['am' => 'ከኢማራት ውጭ', 'ti' => 'ካብ ኢማራት ወጻኢ', 'en' => 'Outside the UAE'],
        'unknown' => ['am' => 'ቦታ አልተገለጸም', 'ti' => 'ቦታ ኣይተገልጸን', 'en' => 'Location not stated'],
    ];

    /** Minimum monthly salary choices for job alerts, in AED; 0 = any. */
    public const SALARY_STEPS = [0, 1500, 2500, 4000, 6000];

    public static function languageFromLabel(string $text): ?string
    {
        $text = trim($text);

        foreach (self::LANGUAGE_LABELS as $code => $label) {
            if ($text === $label) {
                return $code;
            }
        }

        return null;
    }

    /** @param array<string, array<string, string>> $list */
    public static function keyFromLabel(array $list, string $text): ?string
    {
        $text = trim($text);

        foreach ($list as $key => $labels) {
            if (in_array($text, $labels, true)) {
                return $key;
            }
        }

        return null;
    }

    /** @param array<string, array<string, string>> $list */
    public static function label(array $list, ?string $key, string $language): string
    {
        return $list[$key][$language] ?? $list[$key]['en'] ?? (string) $key;
    }

    /**
     * @param  array<string, array<string, string>>  $list
     * @return array<int, array<int, string>> keyboard rows, two labels per row
     */
    public static function rows(array $list, string $language, int $perRow = 2): array
    {
        $labels = array_map(fn (array $labels) => $labels[$language] ?? $labels['en'], array_values($list));

        return array_chunk($labels, $perRow);
    }
}
