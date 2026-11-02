<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\UsaJobs;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class UsaJobsNormalizer implements JobNormalizer
{
    private const array PERIODS = ['PA' => SalaryPeriod::Year, 'PH' => SalaryPeriod::Hour, 'PD' => SalaryPeriod::Day, 'PW' => SalaryPeriod::Week, 'PM' => SalaryPeriod::Month];

    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = Text::clean($job->string('PositionTitle'));
        $company = Text::clean($job->string('OrganizationName'));
        $sourceUrl = $job->string('PositionURI');

        if ($title === null || $company === null || $sourceUrl === null) {
            return null;
        }

        $period = self::PERIODS[$job->string('PositionRemuneration.0.RateIntervalCode') ?? ''] ?? null;

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            description: '<p>'.e(Text::clean($job->string('UserArea.Details.JobSummary') ?? $job->string('QualificationSummary')) ?? '').'</p>',
            applyUrl: $job->string('ApplyURI.0') ?? $sourceUrl,
            sourceUrl: $sourceUrl,
            categoryHint: $job->string('JobCategory.0.Name'),
            locationText: $job->string('PositionLocationDisplay'),
            remoteScope: RemoteScope::CountryRestricted,
            allowedRegions: ['US'],
            salary: $period === null ? null : Salary::tryFrom($job->float('PositionRemuneration.0.MinimumRange'), $job->float('PositionRemuneration.0.MaximumRange'), 'USD', $period),
            postedAt: $job->date('PublicationStartDate'),
            remoteByDefault: true,
        );
    }
}
