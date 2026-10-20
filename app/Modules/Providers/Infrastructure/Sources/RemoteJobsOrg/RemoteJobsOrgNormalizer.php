<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\RemoteJobsOrg;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Services\SalaryParser;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;

final class RemoteJobsOrgNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('title');
        $company = $job->string('company.name');
        $url = $job->string('url');

        if ($title === null || $company === null || $url === null) {
            return null;
        }

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            description: $job->string('description') ?? '',
            applyUrl: $url,
            sourceUrl: $url,
            companyLogoUrl: $job->string('company.logo_url'),
            categoryHint: $job->string('category.name'),
            locationText: $job->string('location'),
            employmentType: EmploymentType::fromLabel($job->string('type') ?? $job->strings('employment_types')[0] ?? null),
            salary: SalaryParser::parse($job->string('salary_text')),
            salaryText: $job->string('salary_text'),
            postedAt: $job->date('posted_at'),
            remoteByDefault: true,
        );
    }
}
