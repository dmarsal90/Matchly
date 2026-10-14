<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Ats\Lever;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;

final class LeverNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('text');
        $url = $job->string('hostedUrl');

        if ($title === null || $url === null) {
            return null;
        }

        $locations = $job->strings('categories.allLocations') ?: array_filter([$job->string('categories.location')]);

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $job->string('_company.name') ?? $job->string('_company.identifier') ?? '',
            description: $this->description($job),
            applyUrl: $job->string('applyUrl') ?? $url,
            sourceUrl: $url,
            categoryHint: $job->string('categories.team') ?? $job->string('categories.department'),
            locationText: implode(' | ', [...$locations, ...array_filter([$job->string('country')])]),
            workMode: match ($job->string('workplaceType')) {
                'remote' => WorkMode::Remote,
                'hybrid' => WorkMode::Hybrid,
                'onsite', 'on-site' => WorkMode::Onsite,
                default => null,
            },
            employmentType: EmploymentType::fromLabel($job->string('categories.commitment')),
            salary: Salary::tryFrom(
                $job->float('salaryRange.min'),
                $job->float('salaryRange.max'),
                $job->string('salaryRange.currency'),
                SalaryPeriod::fromLabel($job->string('salaryRange.interval')),
            ),
            postedAt: $job->date('createdAt'),
        );
    }

    /** Lever splits a posting into an intro, titled lists and closing notes. */
    private function description(Payload $job): string
    {
        $html = $job->string('description') ?? '';

        foreach ($job->array('lists') as $list) {
            if (is_array($list)) {
                $section = Payload::of($list);
                $html .= '<h4>'.e($section->string('text') ?? '').'</h4><ul>'.($section->string('content') ?? '').'</ul>';
            }
        }

        return $html.($job->string('additional') ?? '');
    }
}
