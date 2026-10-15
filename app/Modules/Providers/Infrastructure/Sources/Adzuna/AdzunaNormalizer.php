<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Adzuna;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class AdzunaNormalizer implements JobNormalizer
{
    private const array CURRENCIES = [
        'gb' => 'GBP', 'us' => 'USD', 'ca' => 'CAD', 'au' => 'AUD', 'nz' => 'NZD', 'br' => 'BRL', 'in' => 'INR',
        'mx' => 'MXN', 'pl' => 'PLN', 'ch' => 'CHF', 'sg' => 'SGD', 'za' => 'ZAR',
    ];

    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = Text::clean($job->string('title'));
        $company = Text::clean($job->string('company.display_name'));
        $url = $job->string('redirect_url');

        if ($title === null || $company === null || $url === null) {
            return null;
        }

        $country = strtolower($job->string('_country') ?? '');
        $area = $job->strings('location.area');
        $location = implode(', ', array_filter([$job->string('location.display_name'), $area[0] ?? null]));

        // Predicted salaries are Adzuna's estimate, not the employer's offer.
        $salary = $job->string('salary_is_predicted') === '1'
            ? null
            : Salary::tryFrom($job->float('salary_min'), $job->float('salary_max'), self::CURRENCIES[$country] ?? 'EUR', SalaryPeriod::Year);

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            // Adzuna only returns a snippet; the full posting is behind redirect_url.
            description: '<p>'.e($job->string('description') ?? '').'</p>',
            applyUrl: $url,
            sourceUrl: $url,
            categoryHint: $job->string('category.label'),
            locationText: $location !== '' ? $location : strtoupper($country),
            employmentType: EmploymentType::fromLabel($job->string('contract_time') ?? $job->string('contract_type')),
            salary: $salary,
            postedAt: $job->date('created'),
            // Results come from a "remote" keyword search.
            remoteByDefault: true,
        );
    }
}
