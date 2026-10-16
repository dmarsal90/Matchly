<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Services;

/** One key per company however it is written: "ACME, Inc." and "Acme Inc" match. */
final class CompanyKey
{
    private const array LEGAL_SUFFIXES = ['inc', 'llc', 'ltd', 'gmbh', 'sl', 'slu', 'sa', 'srl', 'bv', 'ag', 'corp', 'co', 'limited', 'ltda', 'lda'];

    public static function from(string $company): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(self::ascii($company)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = array_values(array_filter($words, fn (string $word): bool => ! in_array($word, self::LEGAL_SUFFIXES, true)));

        return mb_substr(implode('-', $kept !== [] ? $kept : $words), 0, 120);
    }

    private static function ascii(string $text): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return $converted === false || $converted === '' ? $text : $converted;
    }
}
