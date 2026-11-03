<?php

namespace App\Jobs\Pipeline;

use App\Models\JobRaw;
use App\Support\JobSettings;

/**
 * The scam score is the larger of two readings: our own rules, which cannot
 * be talked out of anything, and the model's risk, which sees what rules
 * miss. A blocked company or a moderator's phrase always wins.
 */
class ScamScorer
{
    /** Rough monthly medians in AED for the work our members do, to spot "too good to be true". */
    private const MEDIAN_AED = [
        'domestic' => 1800, 'driver' => 3000, 'security' => 2500, 'cleaning' => 1800, 'hospitality' => 2500,
        'salon' => 3000, 'retail' => 3000, 'warehouse' => 2500, 'nursing' => 5000, 'office' => 5000,
        'construction' => 2500, 'other' => 3500,
    ];

    /** @return array{score: int, reasons: array<int, string>} */
    public function score(JobRaw $raw, Classification $c): array
    {
        $text = mb_strtolower($raw->raw_text.' '.$raw->title.' '.$raw->company);
        $reasons = [];
        $rules = 0;

        $add = function (int $points, string $reason) use (&$rules, &$reasons): void {
            $rules += $points;
            $reasons[] = $reason;
        };

        if (preg_match('/\b(registration|processing|visa|medical|application|agency|service|training|placement)\s+(fee|fees|charge|charges|cost)\b|\bpay (a |the )?(fee|deposit)\b|\bdeposit (of|required)\b|\badvance payment\b|ክፍያ/u', $text)) {
            $add(40, 'Asks the applicant for a fee or deposit');
        }

        if (preg_match('/[a-z0-9._%+-]+@(gmail|yahoo|hotmail|outlook|live|icloud|aol|proton)\.[a-z.]+/u', $text)) {
            $add(15, 'Contact through a personal email address');
        }

        if (preg_match('/whats\s?app|wa\.me|ዋትስአፕ/u', $text) && ! preg_match('/https?:\/\/(?!wa\.me|chat\.whatsapp)[a-z0-9.-]+\.[a-z]{2,}/u', $text)) {
            $add(15, 'Contact only through WhatsApp');
        }

        if (preg_match('/\b(no interview|without interview|guaranteed (job|visa)|100% (job|visa)|visa guaranteed|direct hiring no)\b/u', $text)) {
            $add(20, 'Promises a job or visa without an interview');
        }

        $stated = $c->salaryMax ?: $c->salaryMin;
        $median = self::MEDIAN_AED[$c->category] ?? 3500;
        if ($stated && ($c->salaryCurrency === null || $c->salaryCurrency === 'AED') && $stated > $median * 3) {
            $add(20, 'Pay far above normal for this work');
        }

        // A moderator's decision is not a hint: it always reaches the review queue.
        $floor = 0;

        foreach (JobSettings::scamPhrases() as $phrase) {
            if ($phrase !== '' && str_contains($text, $phrase)) {
                $add(25, 'Contains a phrase moderators marked: "'.$phrase.'"');
                $floor = JobSettings::reviewThreshold();
            }
        }

        $company = JobSettings::normaliseCompany($c->company ?? $raw->company);
        if ($company !== '' && in_array($company, JobSettings::blockedCompanies(), true)) {
            $add(60, 'Company was rejected by a moderator before');
            $floor = JobSettings::reviewThreshold();
        }

        foreach ($c->scamSignals as $signal) {
            $reasons[] = 'Model: '.$signal;
        }

        return [
            'score' => max(0, min(100, max($rules, $c->scamRisk, $floor))),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}
