<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Reed;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;
use DateTimeImmutable;
use DateTimeZone;

final class ReedNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = Text::clean($job->string('jobTitle'));
        $company = Text::clean($job->string('employerName'));
        $url = $job->string('jobUrl');

        if ($title === null || $company === null || $url === null) {
            return null;
        }

        // Reed sends dd/mm/yyyy.
        $posted = DateTimeImmutable::createFromFormat('!d/m/Y', (string) $job->string('date'), new DateTimeZone('UTC'));

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            // Search results only carry a snippet; the full posting is behind jobUrl.
            description: '<p>'.e(Text::clean($job->string('jobDescription')) ?? '').'</p>',
            applyUrl: $url,
            sourceUrl: $url,
            locationText: $job->string('locationName') ?? 'United Kingdom',
            remoteScope: RemoteScope::CountryRestricted,
            allowedRegions: ['GB'],
            salary: Salary::tryFrom($job->float('minimumSalary'), $job->float('maximumSalary'), $job->string('currency') ?? 'GBP', SalaryPeriod::Year),
            postedAt: $posted ?: null,
            // Results come from a "remote" keyword search.
            remoteByDefault: true,
        );
    }
}
