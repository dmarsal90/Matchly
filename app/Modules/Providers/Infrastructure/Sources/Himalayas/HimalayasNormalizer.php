<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Himalayas;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class HimalayasNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('title');
        $company = $job->string('companyName');
        $url = $job->string('applicationLink') ?? $job->string('guid');

        if ($title === null || $company === null || $url === null) {
            return null;
        }

        $restrictions = $job->strings('locationRestrictions');
        $categories = $job->strings('parentCategories');

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            description: $job->string('description') ?? '',
            applyUrl: $url,
            sourceUrl: $job->string('guid') ?? $url,
            companyLogoUrl: $job->string('companyLogo'),
            categoryHint: $categories[0] ?? null,
            locationText: $restrictions === [] ? 'Worldwide' : implode(', ', $restrictions),
            workMode: WorkMode::Remote,
            // Himalayas lists location restrictions explicitly; none means anywhere.
            remoteScope: $restrictions === [] ? RemoteScope::Worldwide : null,
            allowedRegions: $restrictions,
            employmentType: EmploymentType::fromLabel($job->string('employmentType')),
            seniority: Seniority::fromLabel($job->strings('seniority')[0] ?? null),
            salary: Salary::tryFrom(
                $job->float('minSalary'),
                $job->float('maxSalary'),
                $job->string('currency'),
                SalaryPeriod::fromLabel($job->string('salaryPeriod')),
            ),
            tags: Text::tags([...$categories, ...array_map(
                fn (string $category): string => str_replace('-', ' ', $category),
                array_slice($job->strings('categories'), 0, 5),
            )]),
            postedAt: $job->date('pubDate'),
            remoteByDefault: true,
        );
    }
}
