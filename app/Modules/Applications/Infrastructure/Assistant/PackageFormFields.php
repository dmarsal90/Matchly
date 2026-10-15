<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Assistant;

use App\Modules\Applications\Domain\Contracts\ApplicationAssistant;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;

/** Maps an approved package to the fields most application forms ask for. */
final class PackageFormFields implements ApplicationAssistant
{
    public function fieldsFor(ApplicationPackage $package): array
    {
        $resume = $package->resumeData();
        if ($resume === null) {
            return [];
        }

        $name = trim($resume->contact['name']);
        $parts = preg_split('/\s+/u', $name) ?: [];
        $links = [];
        foreach ($resume->contact['links'] as $link) {
            $links[mb_strtolower($link['label'])] = $link['url'];
        }

        $profile = $package->user?->profile;
        $salary = $profile !== null && $profile->salary_min !== null
            ? trim(number_format($profile->salary_min * ($profile->salary_period ?? SalaryPeriod::Year)->annualFactor(), 0, '.', '').' '.$profile->salary_currency)
            : null;

        return array_filter([
            'full_name' => $name,
            'first_name' => $parts[0] ?? null,
            'last_name' => count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null,
            'email' => $resume->contact['email'],
            'phone' => $resume->contact['phone'],
            'location' => $resume->contact['location'],
            'linkedin' => $links['linkedin'] ?? null,
            'github' => $links['github'] ?? null,
            'portfolio' => $links['portfolio'] ?? null,
            'headline' => $resume->headline,
            'summary' => $resume->summary,
            'cover_letter' => $package->cover_letter,
            'salary_expectation' => $salary,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }
}
