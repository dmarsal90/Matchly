<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Jobicy;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class JobicyNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('jobTitle');
        $company = $job->string('companyName');
        $url = $job->string('url');

        if ($title === null || $company === null || $url === null) {
            return null;
        }

        $industries = $job->strings('jobIndustry');

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: Text::clean($title) ?? $title,
            company: Text::clean($company) ?? $company,
            description: $job->string('jobDescription') ?? '',
            applyUrl: $url,
            sourceUrl: $url,
            companyLogoUrl: $job->string('companyLogo'),
            categoryHint: $industries[0] ?? null,
            locationText: $job->string('jobGeo'),
            employmentType: EmploymentType::fromLabel($job->strings('jobType')[0] ?? null),
            seniority: Seniority::fromLabel($job->string('jobLevel')),
            salary: Salary::tryFrom(
                $job->float('salaryMin'),
                $job->float('salaryMax'),
                $job->string('salaryCurrency'),
                SalaryPeriod::fromLabel($job->string('salaryPeriod')),
            ),
            tags: Text::tags(array_map(fn (string $value): string => Text::clean($value) ?? $value, $industries)),
            postedAt: $job->date('pubDate'),
            remoteByDefault: true,
        );
    }
}
