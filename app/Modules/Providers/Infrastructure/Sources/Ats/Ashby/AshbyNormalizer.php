<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Ats\Ashby;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;

final class AshbyNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('title');
        $url = $job->string('jobUrl');

        if ($title === null || $url === null) {
            return null;
        }

        $secondary = array_filter(array_map(
            fn (mixed $location): ?string => is_array($location) ? Payload::of($location)->string('location') : null,
            $job->array('secondaryLocations'),
        ));

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $job->string('_company.name') ?? $job->string('_company.identifier') ?? '',
            description: $job->string('descriptionHtml') ?? '',
            applyUrl: $job->string('applyUrl') ?? $url,
            sourceUrl: $url,
            categoryHint: $job->string('department') ?? $job->string('team'),
            locationText: implode(' | ', array_filter([
                $job->string('location'),
                ...$secondary,
                $job->string('address.postalAddress.addressCountry'),
            ])),
            workMode: match (true) {
                $job->string('workplaceType') === 'Hybrid' => WorkMode::Hybrid,
                $job->string('workplaceType') === 'Remote', $job->bool('isRemote') === true => WorkMode::Remote,
                $job->string('workplaceType') === 'OnSite' => WorkMode::Onsite,
                default => null,
            },
            employmentType: EmploymentType::fromLabel($job->string('employmentType')),
            salary: $this->salary($job),
            salaryText: $job->string('compensation.scrapeableCompensationSalarySummary'),
            postedAt: $job->date('publishedAt'),
        );
    }

    private function salary(Payload $job): ?Salary
    {
        foreach ($job->array('compensation.summaryComponents') as $component) {
            if (! is_array($component)) {
                continue;
            }

            $component = Payload::of($component);
            if ($component->string('compensationType') === 'Salary') {
                return Salary::tryFrom(
                    $component->float('minValue'),
                    $component->float('maxValue'),
                    $component->string('currencyCode'),
                    SalaryPeriod::fromLabel($component->string('interval')),
                );
            }
        }

        return null;
    }
}
