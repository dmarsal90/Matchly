<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Localization;

use Collator;
use Locale;

/**
 * ISO 3166-1 alpha-2 codes with names localized by ICU, so no translation
 * files are needed for countries.
 */
final class Countries
{
    public const array CODES = [
        'AD', 'AE', 'AF', 'AG', 'AL', 'AM', 'AO', 'AR', 'AT', 'AU', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ',
        'BN', 'BO', 'BR', 'BS', 'BT', 'BW', 'BY', 'BZ', 'CA', 'CD', 'CF', 'CG', 'CH', 'CI', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU',
        'CV', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FR', 'GA', 'GB',
        'GD', 'GE', 'GH', 'GM', 'GN', 'GQ', 'GR', 'GT', 'GW', 'GY', 'HK', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IN', 'IQ',
        'IR', 'IS', 'IT', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KM', 'KN', 'KR', 'KW', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK', 'LR',
        'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MG', 'MK', 'ML', 'MM', 'MN', 'MO', 'MR', 'MT', 'MU', 'MV', 'MW',
        'MX', 'MY', 'MZ', 'NA', 'NE', 'NG', 'NI', 'NL', 'NO', 'NP', 'NZ', 'OM', 'PA', 'PE', 'PG', 'PH', 'PK', 'PL', 'PR', 'PS',
        'PT', 'PY', 'QA', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SI', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR',
        'SS', 'SV', 'SY', 'SZ', 'TD', 'TG', 'TH', 'TJ', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TW', 'TZ', 'UA', 'UG', 'US', 'UY',
        'UZ', 'VE', 'VN', 'VU', 'WS', 'XK', 'YE', 'ZA', 'ZM', 'ZW',
    ];

    /**
     * @return array<string, string> code => localized name, sorted by name
     */
    public static function names(string $locale): array
    {
        $names = [];
        foreach (self::CODES as $code) {
            $name = Locale::getDisplayRegion('-'.$code, $locale);
            $names[$code] = is_string($name) && $name !== '' ? $name : $code;
        }

        $collator = class_exists(Collator::class) ? new Collator($locale) : null;
        $collator !== null ? $collator->asort($names) : asort($names);

        return $names;
    }
}
