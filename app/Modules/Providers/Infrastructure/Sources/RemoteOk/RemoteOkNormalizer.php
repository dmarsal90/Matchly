<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\RemoteOk;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class RemoteOkNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('position');
        $company = $job->string('company');
        $url = $job->string('url');

        if ($title === null || $company === null || $url === null) {
            return null;
        }

        $tags = $job->strings('tags');

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: Text::clean($title) ?? $title,
            company: Text::clean($company) ?? $company,
            description: $job->string('description') ?? '',
            // The terms require linking to the Remote OK page, which then forwards to the employer.
            applyUrl: $url,
            sourceUrl: $url,
            companyLogoUrl: $job->string('company_logo') ?? $job->string('logo'),
            categoryHint: $tags[0] ?? null,
            locationText: $job->string('location'),
            salary: Salary::tryFrom($job->float('salary_min'), $job->float('salary_max'), 'USD', SalaryPeriod::Year),
            tags: Text::tags($tags),
            postedAt: $job->date('date') ?? $job->date('epoch'),
            remoteByDefault: true,
        );
    }
}
