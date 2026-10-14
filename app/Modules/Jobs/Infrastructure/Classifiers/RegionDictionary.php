<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Classifiers;

use App\Modules\Jobs\Domain\Enums\Region;
use Locale;

/**
 * Turns free-text locations ("Remote, France", "Canada,  USA", "EMEA",
 * "Anywhere in the World", "Berlin, Deutschland") into ISO 3166-1 alpha-2
 * country codes and Region values. Country names come from ICU in several
 * languages so the dictionary does not need maintaining by hand.
 */
final class RegionDictionary
{
    private const array ISO_CODES = [
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

    private const array NAME_LOCALES = ['en', 'es', 'de', 'pt', 'fr'];

    /** Common spellings ICU does not produce. */
    private const array COUNTRY_ALIASES = [
        'usa' => 'US', 'u.s.' => 'US', 'u.s.a.' => 'US', 'united states of america' => 'US',
        'uk' => 'GB', 'u.k.' => 'GB', 'great britain' => 'GB', 'britain' => 'GB', 'england' => 'GB',
        'scotland' => 'GB', 'wales' => 'GB', 'northern ireland' => 'GB',
        'uae' => 'AE', 'south korea' => 'KR', 'korea' => 'KR', 'russia' => 'RU', 'czech republic' => 'CZ',
        'holland' => 'NL', 'the netherlands' => 'NL', 'ivory coast' => 'CI', 'vietnam' => 'VN', 'turkey' => 'TR',
        'deutschland' => 'DE', 'españa' => 'ES', 'brasil' => 'BR', 'méxico' => 'MX', 'schweiz' => 'CH', 'österreich' => 'AT',
    ];

    /** Full US state names; two-letter codes are matched separately after a comma. */
    private const array US_STATES = [
        'alabama', 'alaska', 'arizona', 'arkansas', 'california', 'colorado', 'connecticut', 'delaware', 'florida',
        'hawaii', 'idaho', 'illinois', 'indiana', 'iowa', 'kansas', 'kentucky', 'louisiana', 'maine', 'maryland',
        'massachusetts', 'michigan', 'minnesota', 'mississippi', 'missouri', 'montana', 'nebraska', 'nevada',
        'new hampshire', 'new jersey', 'new mexico', 'new york', 'north carolina', 'north dakota', 'ohio', 'oklahoma',
        'oregon', 'pennsylvania', 'rhode island', 'south carolina', 'south dakota', 'tennessee', 'texas', 'utah',
        'vermont', 'virginia', 'washington', 'west virginia', 'wisconsin', 'wyoming', 'district of columbia',
    ];

    private const array US_STATE_CODES = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME',
        'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA',
        'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY', 'DC',
    ];

    private const array CANADIAN_PROVINCES = ['ON', 'QC', 'BC', 'AB', 'MB', 'SK', 'NS', 'NB', 'NL', 'PE'];

    /** @var array<string, string> */
    private const array REGION_PATTERNS = [
        '/\b(emea)\b/' => 'EMEA',
        '/\b(european union)\b/' => 'EU',
        '/\b(europe|european|europa|cet|cest)\b/' => 'EUROPE',
        '/\b(north america|northern america|norteamérica)\b/' => 'NORTH_AMERICA',
        '/\b(latam|latin america|south america|central america|latinoamérica|america latina|américa latina|caribbean)\b/' => 'LATAM',
        '/\b(americas|the americas)\b/' => 'AMERICAS',
        '/\b(apac|asia[- ]pacific)\b/' => 'APAC',
        '/\b(asia|southeast asia|south asia)\b/' => 'ASIA',
        '/\b(africa)\b/' => 'AFRICA',
        '/\b(middle east|mena)\b/' => 'MIDDLE_EAST',
        '/\b(oceania|anz)\b/' => 'OCEANIA',
    ];

    private const string WORLDWIDE_PATTERN = '/\b(worldwide|world[- ]?wide|anywhere|global(ly)?|international|any (location|country|timezone)|weltweit|en cualquier lugar|todo el mundo|mundial)\b/';

    /** @var array<string, string>|null lowercase name => ISO code */
    private ?array $names = null;

    public function isWorldwide(?string $text): bool
    {
        return $text !== null && preg_match(self::WORLDWIDE_PATTERN, mb_strtolower($text)) === 1;
    }

    /**
     * @return list<string> ISO codes and Region values found in the text, in order of appearance
     */
    public function extract(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $lower = mb_strtolower($text);
        $found = [];

        // Countries first, masking each match so "South Africa" does not also
        // count as the "Africa" region and "Latin America" stays a region.
        foreach ($this->names() as $name => $code) {
            $position = $this->find($lower, $name);
            if ($position !== null) {
                $found[$code] ??= $position;
                $lower = substr_replace($lower, str_repeat(' ', strlen($name)), $position, strlen($name));
            }
        }

        foreach (self::US_STATES as $state) {
            $position = $this->find($lower, $state);
            if ($position !== null) {
                $found['US'] ??= $position;
            }
        }

        foreach (self::REGION_PATTERNS as $pattern => $region) {
            if (preg_match($pattern, $lower, $match, PREG_OFFSET_CAPTURE) === 1) {
                $found[$region] ??= $match[0][1];
            }
        }

        // "European Union" also matches the broader "Europe" pattern.
        if (isset($found[Region::EuropeanUnion->value])) {
            unset($found[Region::Europe->value]);
        }
        // "Americas" must not swallow "North/Latin America".
        if (isset($found[Region::NorthAmerica->value]) || isset($found[Region::LatinAmerica->value])) {
            unset($found[Region::Americas->value]);
        }

        // Uppercase codes only count as whole tokens in the original text ("Remote - US", "UK only", "Austin, TX").
        if (preg_match_all('/(?<![A-Za-z])([A-Z]{2})(?![A-Za-z])/', $text, $codes, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($codes[1] as [$code, $offset]) {
                $iso = match (true) {
                    $code === 'UK' => 'GB',
                    $code === 'EU' => 'EU',
                    in_array($code, self::CANADIAN_PROVINCES, true) && $this->followsComma($text, $offset) => 'CA',
                    in_array($code, self::US_STATE_CODES, true) && $this->followsComma($text, $offset) => 'US',
                    in_array($code, self::ISO_CODES, true) => $code,
                    default => null,
                };

                if ($iso !== null && ! isset($found[$iso])) {
                    $found[$iso] = $offset;
                }
            }
        }

        asort($found);

        return array_keys($found);
    }

    /**
     * Resolves one explicit value from a source (country name, ISO code or region label).
     */
    public function resolve(string $value): ?string
    {
        $trimmed = trim($value);

        if (preg_match('/^[A-Za-z]{2}$/', $trimmed) === 1) {
            $upper = strtoupper($trimmed);

            return $upper === 'UK' ? 'GB' : (in_array($upper, self::ISO_CODES, true) ? $upper : null);
        }

        return $this->extract($trimmed)[0] ?? null;
    }

    private function find(string $haystack, string $needle): ?int
    {
        if (preg_match('/(?<![\p{L}])'.preg_quote($needle, '/').'(?![\p{L}])/u', $haystack, $match, PREG_OFFSET_CAPTURE) === 1) {
            return $match[0][1];
        }

        return null;
    }

    private function followsComma(string $text, int $offset): bool
    {
        return preg_match('/,\s*$/', substr($text, 0, $offset)) === 1;
    }

    /**
     * @return array<string, string>
     */
    private function names(): array
    {
        if ($this->names !== null) {
            return $this->names;
        }

        $names = self::COUNTRY_ALIASES;

        foreach (self::ISO_CODES as $code) {
            foreach (self::NAME_LOCALES as $locale) {
                $name = Locale::getDisplayRegion('-'.$code, $locale);
                if (is_string($name) && $name !== '' && $name !== $code) {
                    $names[mb_strtolower($name)] = $code;
                }
            }
        }

        // Georgia is also a US state; ICU's "Georgia" stays the country, "georgia, us" still hits US via the code.
        // Longest names first so "south africa" wins over "africa", "united states" over "states".
        uksort($names, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $this->names = $names;
    }
}
