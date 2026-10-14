<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

/** Preset publication-date windows for the listing filter. */
enum DateRange: string
{
    case Last24Hours = '24h';
    case Last3Days = '3d';
    case Last7Days = '7d';
    case Last14Days = '14d';
    case Last30Days = '30d';

    public function hours(): int
    {
        return match ($this) {
            self::Last24Hours => 24,
            self::Last3Days => 72,
            self::Last7Days => 168,
            self::Last14Days => 336,
            self::Last30Days => 720,
        };
    }
}
