<?php

namespace App\Jobs\Pipeline;

use App\Models\JobRaw;

/**
 * Keyword rules, used when no Anthropic key is set (development) and as the
 * fallback when the model cannot classify a listing. It never writes an
 * Amharic or Tigrinya summary: the card falls back to English, and a listing
 * classified this way after a model failure goes to review.
 */
class RulesJobClassifier implements JobClassifier
{
    private const CATEGORY_WORDS = [
        'domestic' => ['housemaid', 'house maid', 'maid', 'nanny', 'babysitter', 'domestic', 'housekeeper for family', 'cook for family', 'ሰራተኛ', 'ሞግዚት'],
        'driver' => ['driver', 'chauffeur', 'delivery rider', 'bike rider', 'motorcycle', 'heavy vehicle', 'light vehicle', 'ሹፌር'],
        'security' => ['security guard', 'security officer', 'guard', 'sira', 'ጥበቃ'],
        'cleaning' => ['cleaner', 'cleaning', 'janitor', 'housekeeping', 'ጽዳት'],
        'hospitality' => ['waiter', 'waitress', 'barista', 'chef', 'cook', 'kitchen', 'restaurant', 'hotel', 'housekeeping attendant', 'steward', 'cafe'],
        'salon' => ['salon', 'beautician', 'hair', 'nail', 'makeup', 'spa', 'massage', 'barber'],
        'retail' => ['sales', 'cashier', 'shop', 'store', 'retail', 'merchandiser', 'promoter'],
        'warehouse' => ['warehouse', 'picker', 'packer', 'storekeeper', 'logistics', 'loader', 'courier'],
        'nursing' => ['nurse', 'caregiver', 'care giver', 'elderly care', 'patient care', 'nursing'],
        'office' => ['receptionist', 'admin', 'secretary', 'accountant', 'data entry', 'office', 'customer service', 'call center'],
        'construction' => ['electrician', 'plumber', 'mason', 'carpenter', 'technician', 'construction', 'welder', 'painter', 'helper', 'labour', 'labor'],
    ];

    private const EMIRATE_WORDS = [
        'dubai' => ['dubai', 'deira', 'bur dubai', 'jumeirah', 'al quoz', 'jebel ali', 'marina', 'karama', 'satwa', 'ዱባይ'],
        'sharjah' => ['sharjah', 'ሻርጃ'],
        'abu_dhabi' => ['abu dhabi', 'al ain', 'mussafah', 'አቡ ዳቢ'],
        'ajman' => ['ajman'],
        'other_uae' => ['ras al khaimah', 'rak', 'fujairah', 'umm al quwain', 'uaq'],
    ];

    public function classify(JobRaw $raw): Classification
    {
        $text = mb_strtolower(($raw->title ?? '').' '.($raw->location ?? '').' '.$raw->raw_text);

        [$min, $max, $currency] = $this->salary($text);

        return Classification::fromArray([
            'is_job' => true,
            'category' => $this->match(self::CATEGORY_WORDS, mb_strtolower($raw->title ?? '').' '.$text) ?? 'other',
            'emirate' => $this->match(self::EMIRATE_WORDS, mb_strtolower($raw->location ?? '').' '.$text) ?? 'unknown',
            'title' => $raw->title ?: mb_substr(strtok($raw->raw_text, "\n") ?: $raw->raw_text, 0, 120),
            'company' => $raw->company ?? '',
            'salary_min' => $min,
            'salary_max' => $max,
            'salary_currency' => $currency,
            'accommodation' => preg_match('/\b(accommodation|housing) (provided|included|free)\b|free accommodation/u', $text) ? 'yes' : 'unknown',
            'visa' => preg_match('/\b(visa (provided|included|by company)|company visa|free visa)\b/u', $text) ? 'yes' : 'unknown',
            'languages' => [],
            'summary_en' => mb_substr(preg_replace('/\s+/u', ' ', $raw->raw_text) ?? '', 0, 400),
            'scam_risk' => 0,
            'scam_signals' => [],
        ], 'rules');
    }

    /** @param array<string, array<int, string>> $map */
    private function match(array $map, string $text): ?string
    {
        foreach ($map as $key => $words) {
            foreach ($words as $word) {
                if (preg_match('/(?<![\p{L}])'.preg_quote($word, '/').'(?![\p{L}])/u', $text)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** @return array{0: int, 1: int, 2: string} */
    private function salary(string $text): array
    {
        $amount = '(\d{1,2}[,.]?\d{3}|\d{3,5})';

        if (preg_match('/(aed|dhs?|dirhams?)\s*'.$amount.'(?:\s*(?:-|–|to)\s*(?:aed|dhs?)?\s*'.$amount.')?/u', $text, $m)
            || preg_match('/'.$amount.'(?:\s*(?:-|–|to)\s*'.$amount.')?\s*(aed|dhs?|dirhams?)/u', $text, $m2)) {
            if (isset($m2)) {
                $low = $m2[1];
                $high = $m2[2] ?? '';
            } else {
                $low = $m[2];
                $high = $m[3] ?? '';
            }

            $toInt = fn (string $v): int => (int) str_replace([',', '.'], '', $v);

            return [$toInt($low), $high !== '' ? $toInt($high) : 0, 'AED'];
        }

        return [0, 0, ''];
    }
}
