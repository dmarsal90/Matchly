<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Services;

/**
 * Identifies the same posting published by several sources: title and company
 * reduced to their meaningful words. Paired with the canonical apply URL by
 * the repository to detect cross-source duplicates.
 */
final class JobFingerprint
{
    private const array COMPANY_SUFFIXES = [
        'inc', 'incorporated', 'llc', 'ltd', 'limited', 'gmbh', 'ag', 'sa', 'sas', 'sl', 'srl', 'bv', 'oy', 'ab',
        'plc', 'corp', 'corporation', 'co', 'company', 'pty', 'kg', 'se',
    ];

    private const array TITLE_NOISE = ['remote', 'hybrid', 'mwd', 'fmd', 'mfd', 'wmd', 'gn', 'fully', 'anywhere', 'worldwide', 'f', 'm', 'w', 'd'];

    public static function make(string $title, string $company): string
    {
        return hash('xxh128', self::normalizeTitle($title).'|'.self::normalizeCompany($company));
    }

    public static function normalizeTitle(string $title): string
    {
        $words = self::words($title);

        return implode(' ', array_values(array_diff($words, self::TITLE_NOISE)));
    }

    public static function normalizeCompany(string $company): string
    {
        $words = self::words($company);

        return implode(' ', array_values(array_diff($words, self::COMPANY_SUFFIXES)));
    }

    /**
     * @return list<string>
     */
    private static function words(string $value): array
    {
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value) ?: strtolower($value);

        return array_values(array_filter(
            preg_split('/[^a-z0-9+#]+/', $ascii) ?: [],
            fn (string $word): bool => $word !== '',
        ));
    }
}
