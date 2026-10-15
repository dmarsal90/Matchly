<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

enum SalaryPeriod: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /** Approximate multiplier to an annual figure (full-time). */
    public function annualFactor(): int
    {
        return match ($this) {
            self::Hour => 2080,
            self::Day => 260,
            self::Week => 52,
            self::Month => 12,
            self::Year => 1,
        };
    }

    public static function fromLabel(?string $label): ?self
    {
        if ($label === null) {
            return null;
        }

        $value = strtolower($label);

        return match (true) {
            (bool) preg_match('/hour|hr\b|hourly|\/h\b/', $value) => self::Hour,
            (bool) preg_match('/\bday|daily/', $value) => self::Day,
            (bool) preg_match('/week/', $value) => self::Week,
            (bool) preg_match('/month|monthly|\/mo\b/', $value) => self::Month,
            (bool) preg_match('/year|annual|yearly|\/yr\b|p\.?a\.?/', $value) => self::Year,
            default => null,
        };
    }
}
