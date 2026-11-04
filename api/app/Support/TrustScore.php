<?php

namespace App\Support;

/**
 * How stars become a place in a list, for members and businesses alike.
 *
 * A plain average lets one 5-star rating beat twenty 4.8s, so everyone starts as
 * if they already held a few good ratings, and real ones move them from there
 * (a Bayesian average). A newcomer sits below someone proven and above someone
 * with poor ratings. The lists, the SQL and the badge all read these numbers.
 */
final class TrustScore
{
    /** How many ratings everyone is assumed to start with, and how many stars those are. */
    public const PRIOR_COUNT = 3;

    public const PRIOR_STARS = 4.0;

    /** What earns the "top rated" badge. */
    public const TOP_MIN_RATINGS = 3;

    public const TOP_MIN_STARS = 4.5;

    public static function of(float|string|null $average, int|string|null $count): float
    {
        $count = (int) $count;

        return (self::PRIOR_COUNT * self::PRIOR_STARS + (float) $average * $count) / (self::PRIOR_COUNT + $count);
    }

    /** The same sum for an ORDER BY, over the caller's columns. */
    public static function sql(string $average, string $count): string
    {
        return sprintf('((%d * %F + coalesce(%s, 0) * %s) / (%d + %s))', self::PRIOR_COUNT, self::PRIOR_STARS, $average, $count, self::PRIOR_COUNT, $count);
    }

    public static function isTop(float|string|null $average, int|string|null $count): bool
    {
        return (int) $count >= self::TOP_MIN_RATINGS && (float) $average >= self::TOP_MIN_STARS;
    }
}
