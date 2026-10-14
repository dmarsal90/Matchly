<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Services;

use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;

/**
 * Parses free-text salaries such as "$35,3k- $52k", "€60.000 - €75.000 / year",
 * "USD 40-50 per hour" or "120k". Returns null when nothing reliable is found;
 * callers keep the original text for display.
 */
final class SalaryParser
{
    private const array SYMBOLS = ['$' => 'USD', '€' => 'EUR', '£' => 'GBP', '¥' => 'JPY', '₹' => 'INR', 'R$' => 'BRL'];

    public static function parse(?string $text, ?string $defaultCurrency = null): ?Salary
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $currency = self::currency($text) ?? $defaultCurrency;

        preg_match_all('/(\d+(?:[.,\s]\d+)*)\s*([kK])?/u', $text, $matches, PREG_SET_ORDER);

        $amounts = [];
        foreach ($matches as $match) {
            $amount = self::toNumber($match[1], ($match[2] ?? '') !== '');
            if ($amount !== null && $amount >= 1) {
                $amounts[] = $amount;
            }
        }

        if ($amounts === []) {
            return null;
        }

        $period = SalaryPeriod::fromLabel($text) ?? self::guessPeriod(max($amounts));

        return Salary::tryFrom(min($amounts), count($amounts) > 1 ? max($amounts) : null, $currency, $period);
    }

    private static function currency(string $text): ?string
    {
        if (preg_match('/\b(USD|EUR|GBP|CAD|AUD|CHF|BRL|MXN|INR|PLN|SEK|NOK|DKK|JPY|ARS|COP|CLP)\b/i', $text, $code) === 1) {
            return strtoupper($code[1]);
        }

        if (str_contains($text, 'R$')) {
            return 'BRL';
        }

        foreach (self::SYMBOLS as $symbol => $code) {
            if (str_contains($text, $symbol)) {
                return $code;
            }
        }

        return null;
    }

    private static function toNumber(string $raw, bool $thousands): ?float
    {
        $raw = (string) preg_replace('/\s+/', '', $raw);

        // "35,3k" / "35.3k": the separator is a decimal point when followed by 1-2 digits.
        if ($thousands && preg_match('/^(\d+)[.,](\d{1,2})$/', $raw, $parts) === 1) {
            return (float) "{$parts[1]}.{$parts[2]}" * 1000;
        }

        // "60.000", "60,000", "1.200.000": separators followed by groups of three digits.
        if (preg_match('/^\d{1,3}(?:[.,]\d{3})+$/', $raw) === 1) {
            $value = (float) preg_replace('/[.,]/', '', $raw);
        } elseif (preg_match('/^(\d+)[.,](\d{1,2})$/', $raw, $parts) === 1) {
            $value = (float) "{$parts[1]}.{$parts[2]}";
        } elseif (ctype_digit($raw)) {
            $value = (float) $raw;
        } else {
            return null;
        }

        return $thousands ? $value * 1000 : $value;
    }

    private static function guessPeriod(float $amount): SalaryPeriod
    {
        return match (true) {
            $amount < 300 => SalaryPeriod::Hour,
            $amount < 20000 => SalaryPeriod::Month,
            default => SalaryPeriod::Year,
        };
    }
}
