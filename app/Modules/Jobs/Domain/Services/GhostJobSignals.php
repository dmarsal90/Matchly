<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Services;

/**
 * Signs that a posting may be dead or never meant to be filled: it has been up
 * a long time, or the same company keeps posting the same title. These are
 * hints, not proof: the answer says "possibly stale", never "fake".
 */
final class GhostJobSignals
{
    public const string OLD = 'old';

    public const string VERY_OLD = 'very_old';

    public const string REPOSTED = 'reposted';

    private const int OLD_DAYS = 21;

    private const int VERY_OLD_DAYS = 60;

    /** Other postings with the same company and title before it counts as a repost. */
    private const int REPOST_COUNT = 2;

    /**
     * @param  int  $ageDays  days since it was posted (or first seen when the source gives no date)
     * @param  int  $otherListings  other postings with the same company and title
     * @return array{level: string, signals: list<string>}|null null when nothing is worth saying
     */
    public static function assess(int $ageDays, int $otherListings): ?array
    {
        $signals = [];
        $points = 0;

        if ($ageDays >= self::VERY_OLD_DAYS) {
            $signals[] = self::VERY_OLD;
            $points += 2;
        } elseif ($ageDays >= self::OLD_DAYS) {
            $signals[] = self::OLD;
            $points += 1;
        }

        if ($otherListings >= self::REPOST_COUNT) {
            $signals[] = self::REPOSTED;
            $points += 2;
        }

        // One mild signal on its own (a three-week-old posting) is not worth a warning.
        return $points >= 2 ? ['level' => $points >= 3 ? 'high' : 'medium', 'signals' => $signals] : null;
    }
}
