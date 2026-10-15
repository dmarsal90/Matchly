<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

/**
 * Multi-country areas. Stored next to ISO 3166-1 alpha-2 country codes in
 * allowed_regions; values are longer than two characters so they never clash.
 */
enum Region: string
{
    case Europe = 'EUROPE';
    case EuropeanUnion = 'EU';
    case Emea = 'EMEA';
    case NorthAmerica = 'NORTH_AMERICA';
    case LatinAmerica = 'LATAM';
    case Americas = 'AMERICAS';
    case Apac = 'APAC';
    case Asia = 'ASIA';
    case Africa = 'AFRICA';
    case MiddleEast = 'MIDDLE_EAST';
    case Oceania = 'OCEANIA';
}
