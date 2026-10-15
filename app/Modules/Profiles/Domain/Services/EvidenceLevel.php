<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Services;

/**
 * Rough 1-5 level estimates from evidence. They are deliberately
 * conservative: they back up a claim, they never replace the user's own
 * assessment, and the user sees and can correct the difference.
 */
final class EvidenceLevel
{
    /** From how many recent repositories use the skill. */
    public static function fromRepositories(int $repositories, ?int $lastUsedYear, int $currentYear): int
    {
        $level = match (true) {
            $repositories >= 8 => 5,
            $repositories >= 4 => 4,
            $repositories >= 2 => 3,
            default => 2,
        };

        return self::decay($level, $lastUsedYear, $currentYear);
    }

    /** From years of professional use (job positions mentioning it). */
    public static function fromYears(float $years, ?int $lastUsedYear, int $currentYear): int
    {
        $level = match (true) {
            $years >= 6 => 5,
            $years >= 3 => 4,
            $years >= 1 => 3,
            default => 2,
        };

        return self::decay($level, $lastUsedYear, $currentYear);
    }

    /** Skills not used for three years or more lose a level. */
    private static function decay(int $level, ?int $lastUsedYear, int $currentYear): int
    {
        if ($lastUsedYear !== null && $currentYear - $lastUsedYear >= 3) {
            $level--;
        }

        return max(1, $level);
    }
}
