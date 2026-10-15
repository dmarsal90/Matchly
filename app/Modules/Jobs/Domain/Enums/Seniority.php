<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

enum Seniority: string
{
    case Intern = 'intern';
    case Junior = 'junior';
    case Mid = 'mid';
    case Senior = 'senior';
    case Lead = 'lead';
    case Manager = 'manager';
    case Director = 'director';
    case Executive = 'executive';

    /** Ordinal used to compare levels when matching profiles. */
    public function rank(): int
    {
        return match ($this) {
            self::Intern => 0,
            self::Junior => 1,
            self::Mid => 2,
            self::Senior => 3,
            self::Lead, self::Manager => 4,
            self::Director => 5,
            self::Executive => 6,
        };
    }

    public static function fromLabel(?string $label): ?self
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $value = strtolower($label);

        return match (true) {
            (bool) preg_match('/\b(intern|internship|trainee|student|werkstudent)\b/', $value) => self::Intern,
            // "Account Executive" is a sales role, not a C-level one: only explicit titles count.
            (bool) preg_match('/\b(ceo|cto|cfo|coo|cmo|chief|vp|svp|evp|vice president|executive director)\b/', $value) => self::Executive,
            (bool) preg_match('/\b(director|head of)\b/', $value) => self::Director,
            (bool) preg_match('/\b(lead|staff|principal|architect)\b/', $value) => self::Lead,
            (bool) preg_match('/\b(manager|management)\b/', $value) => self::Manager,
            (bool) preg_match('/\b(senior|sr\.?|expert)\b/', $value) => self::Senior,
            (bool) preg_match('/\b(junior|jr\.?|entry[- ]level|graduate|entry)\b/', $value) => self::Junior,
            (bool) preg_match('/\b(mid[- ]?level|mid|intermediate|middle|associate)\b/', $value) => self::Mid,
            default => null,
        };
    }
}
