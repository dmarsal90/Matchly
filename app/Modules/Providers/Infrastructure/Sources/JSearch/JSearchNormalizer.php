<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\JSearch;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class JSearchNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = Text::clean($job->string('job_title'));
        $company = Text::clean($job->string('employer_name'));
        $url = $job->string('job_apply_link');

        if ($title === null || $company === null || $url === null) {
            return null;
        }

        $location = implode(', ', array_filter([$job->string('job_city'), $job->string('job_state'), $job->string('job_country')]));

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            description: '<p>'.nl2br(e($job->string('job_description') ?? '')).'</p>',
            applyUrl: $url,
            sourceUrl: $job->string('job_google_link') ?? $url,
            locationText: $location !== '' ? $location : ($job->string('job_location') ?? ''),
            employmentType: EmploymentType::fromLabel($job->string('job_employment_type')),
            salary: Salary::tryFrom(
                $job->float('job_min_salary'),
                $job->float('job_max_salary'),
                $job->string('job_salary_currency') ?? 'USD',
                SalaryPeriod::fromLabel($job->string('job_salary_period')),
            ),
            postedAt: $job->date('job_posted_at_datetime_utc'),
            remoteByDefault: $job->bool('job_is_remote') === true,
        );
    }
}
