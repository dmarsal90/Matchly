<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\LinkedIn;

use Carbon\CarbonImmutable;

/**
 * Dates as LinkedIn exports them: "Mar 2020", "2020", "03/2020", or month
 * names in the export's language. Normalised to "YYYY-MM" or "YYYY".
 */
final class LinkedInDate
{
    private const array MONTHS = [
        'jan' => 1, 'ene' => 1, 'janv' => 1, 'gen' => 1,
        'feb' => 2, 'fev' => 2, 'fév' => 2, 'févr' => 2,
        'mar' => 3, 'mär' => 3, 'mars' => 3,
        'apr' => 4, 'abr' => 4, 'avr' => 4,
        'may' => 5, 'mai' => 5, 'mag' => 5,
        'jun' => 6, 'juin' => 6, 'giu' => 6,
        'jul' => 7, 'juil' => 7, 'lug' => 7,
        'aug' => 8, 'ago' => 8, 'août' => 8,
        'sep' => 9, 'sept' => 9, 'set' => 9,
        'oct' => 10, 'out' => 10, 'okt' => 10, 'ott' => 10,
        'nov' => 11,
        'dec' => 12, 'dic' => 12, 'dez' => 12, 'déc' => 12,
    ];

    public static function parse(string $value): ?string
    {
        $value = mb_strtolower(trim($value));
        if (preg_match('/\b((?:19|20)\d{2})\b/', $value, $year) !== 1) {
            return null;
        }

        if (preg_match('/\b(\d{1,2})[\/.-]'.$year[1].'/', $value, $numeric) === 1 && (int) $numeric[1] >= 1 && (int) $numeric[1] <= 12) {
            return sprintf('%s-%02d', $year[1], (int) $numeric[1]);
        }

        if (preg_match('/([\p{L}]+)\.?\s+'.$year[1].'/u', $value, $named) === 1) {
            $month = self::MONTHS[$named[1]] ?? self::MONTHS[mb_substr($named[1], 0, 3)] ?? null;
            if ($month !== null) {
                return sprintf('%s-%02d', $year[1], $month);
            }
        }

        return $year[1];
    }

    /**
     * Length in years of a period (open-ended = until now) and its end year.
     *
     * @return array{0: float, 1: int|null}
     */
    public static function span(?string $start, ?string $end): array
    {
        $from = self::toDate($start);
        if ($from === null) {
            return [0.0, self::toDate($end)?->year];
        }

        $to = self::toDate($end, endOfPeriod: true) ?? CarbonImmutable::now();
        if ($to->lessThan($from)) {
            return [0.0, $to->year];
        }

        return [round($from->diffInMonths($to) / 12, 1), $to->year];
    }

    private static function toDate(?string $value, bool $endOfPeriod = false): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        $date = strlen($value) === 4
            ? CarbonImmutable::create((int) $value, $endOfPeriod ? 12 : 1, 1)
            : CarbonImmutable::createFromFormat('!Y-m', $value);

        if (! $date instanceof CarbonImmutable) {
            return null;
        }

        return $endOfPeriod ? $date->endOfMonth() : $date;
    }
}
