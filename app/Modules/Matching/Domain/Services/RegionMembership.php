<?php

declare(strict_types=1);

namespace App\Modules\Matching\Domain\Services;

use App\Modules\Jobs\Domain\Enums\Region;

/** Which hiring regions a country belongs to ("ES" → EU, Europe, EMEA). */
final class RegionMembership
{
    private const array EU = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];

    private const array EUROPE_NON_EU = ['GB', 'NO', 'CH', 'IS', 'LI', 'UA', 'RS', 'BA', 'ME', 'MK', 'AL', 'MD', 'XK', 'AD', 'MC', 'SM', 'VA', 'BY', 'GE', 'AM', 'TR'];

    private const array NORTH_AMERICA = ['US', 'CA', 'MX'];

    private const array LATAM = ['MX', 'AR', 'BO', 'BR', 'CL', 'CO', 'CR', 'CU', 'DO', 'EC', 'SV', 'GT', 'HN', 'NI', 'PA', 'PY', 'PE', 'PR', 'UY', 'VE', 'JM', 'TT', 'BZ', 'GY', 'SR', 'HT', 'BS', 'BB'];

    private const array ASIA = ['CN', 'JP', 'KR', 'IN', 'PK', 'BD', 'LK', 'NP', 'ID', 'MY', 'SG', 'TH', 'VN', 'PH', 'KH', 'LA', 'MM', 'TW', 'HK', 'MO', 'MN', 'KZ', 'UZ', 'KG', 'TJ', 'TM', 'AZ'];

    private const array OCEANIA = ['AU', 'NZ', 'FJ', 'PG', 'WS', 'TO'];

    private const array MIDDLE_EAST = ['AE', 'SA', 'QA', 'KW', 'BH', 'OM', 'IL', 'JO', 'LB', 'IQ', 'IR', 'YE', 'SY', 'PS', 'EG', 'TR'];

    private const array AFRICA = ['ZA', 'NG', 'KE', 'GH', 'EG', 'MA', 'TN', 'DZ', 'ET', 'UG', 'TZ', 'RW', 'SN', 'CI', 'CM', 'ZW', 'ZM', 'MZ', 'AO', 'NA', 'BW', 'MU'];

    /**
     * @return list<Region>
     */
    public static function regionsOf(string $country): array
    {
        $country = strtoupper($country);
        $in = fn (array $list): bool => in_array($country, $list, true);

        $europe = $in(self::EU) || $in(self::EUROPE_NON_EU);
        $americas = $in(self::NORTH_AMERICA) || $in(self::LATAM);

        return array_values(array_filter([
            $in(self::EU) ? Region::EuropeanUnion : null,
            $europe ? Region::Europe : null,
            $europe || $in(self::MIDDLE_EAST) || $in(self::AFRICA) ? Region::Emea : null,
            $in(self::NORTH_AMERICA) ? Region::NorthAmerica : null,
            $in(self::LATAM) ? Region::LatinAmerica : null,
            $americas ? Region::Americas : null,
            $in(self::ASIA) || $in(self::OCEANIA) ? Region::Apac : null,
            $in(self::ASIA) ? Region::Asia : null,
            $in(self::AFRICA) ? Region::Africa : null,
            $in(self::MIDDLE_EAST) ? Region::MiddleEast : null,
            $in(self::OCEANIA) ? Region::Oceania : null,
        ]));
    }

    public static function contains(Region $region, string $country): bool
    {
        return in_array($region, self::regionsOf($country), true);
    }

    /** Whether one region lies inside another (EU ⊂ Europe ⊂ EMEA, LATAM ⊂ Americas…). */
    public static function within(Region $inner, Region $outer): bool
    {
        if ($inner === $outer) {
            return true;
        }

        $parents = [
            Region::EuropeanUnion->value => [Region::Europe, Region::Emea],
            Region::Europe->value => [Region::Emea],
            Region::Africa->value => [Region::Emea],
            Region::MiddleEast->value => [Region::Emea],
            Region::NorthAmerica->value => [Region::Americas],
            Region::LatinAmerica->value => [Region::Americas],
            Region::Asia->value => [Region::Apac],
            Region::Oceania->value => [Region::Apac],
        ];

        return in_array($outer, $parents[$inner->value] ?? [], true);
    }
}
