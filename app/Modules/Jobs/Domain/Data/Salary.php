<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Data;

use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use InvalidArgumentException;

final readonly class Salary
{
    public function __construct(
        public ?float $min,
        public ?float $max,
        public ?string $currency,
        public SalaryPeriod $period = SalaryPeriod::Year,
    ) {
        if ($min === null && $max === null) {
            throw new InvalidArgumentException('A salary needs at least a minimum or a maximum.');
        }

        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('Salary minimum cannot exceed the maximum.');
        }
    }

    /**
     * Builds a salary from numbers that may be missing, zero or reversed,
     * as sources often send them. Returns null when nothing usable is left.
     */
    public static function tryFrom(mixed $min, mixed $max, ?string $currency, ?SalaryPeriod $period = null): ?self
    {
        $min = is_numeric($min) && (float) $min > 0 ? (float) $min : null;
        $max = is_numeric($max) && (float) $max > 0 ? (float) $max : null;

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        $currency = $currency !== null && preg_match('/^[A-Za-z]{3}$/', trim($currency)) === 1
            ? strtoupper(trim($currency))
            : null;

        return new self($min, $max, $currency, self::plausiblePeriod($period ?? SalaryPeriod::Year, $max ?? $min));
    }

    /**
     * Sources mislabel periods ("hourly" with 41600): amounts impossible for
     * the stated period are re-read as the period they obviously belong to.
     */
    private static function plausiblePeriod(SalaryPeriod $period, float $amount): SalaryPeriod
    {
        return match (true) {
            $period === SalaryPeriod::Hour && $amount > 1000,
            $period === SalaryPeriod::Day && $amount > 5000,
            $period === SalaryPeriod::Week && $amount > 20000 => $amount >= 20000 ? SalaryPeriod::Year : SalaryPeriod::Month,
            $period === SalaryPeriod::Month && $amount > 100000 => SalaryPeriod::Year,
            default => $period,
        };
    }

    public function annualMin(): ?float
    {
        return $this->min !== null ? $this->min * $this->period->annualFactor() : null;
    }

    public function annualMax(): ?float
    {
        return $this->max !== null ? $this->max * $this->period->annualFactor() : null;
    }
}
