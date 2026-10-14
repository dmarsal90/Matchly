<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Freelance = 'freelance';
    case Internship = 'internship';
    case Temporary = 'temporary';

    /**
     * Maps the free-text labels sources use ("Full-Time", "full_time",
     * "FullTime", "Unbefristeter Arbeitsvertrag", "permanent"...).
     */
    public static function fromLabel(?string $label): ?self
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $value = strtolower((string) preg_replace('/[^a-z]+/i', '', $label));

        return match (true) {
            str_contains($value, 'intern'), str_contains($value, 'praktik'), str_contains($value, 'trainee') => self::Internship,
            str_contains($value, 'freelanc'), str_contains($value, 'selfemployed') => self::Freelance,
            str_contains($value, 'contract'), str_contains($value, 'contractor') => self::Contract,
            str_contains($value, 'temp'), str_contains($value, 'befristet') && ! str_contains($value, 'unbefristet') => self::Temporary,
            str_contains($value, 'parttime'), str_contains($value, 'teilzeit') => self::PartTime,
            str_contains($value, 'fulltime'), str_contains($value, 'permanent'), str_contains($value, 'vollzeit'),
            str_contains($value, 'unbefristet'), str_contains($value, 'regular') => self::FullTime,
            default => null,
        };
    }
}
