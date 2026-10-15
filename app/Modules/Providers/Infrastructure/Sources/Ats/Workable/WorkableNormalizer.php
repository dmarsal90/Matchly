<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Ats\Workable;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;

final class WorkableNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('title');
        $url = $job->string('url') ?? $job->string('shortlink');

        if ($title === null || $url === null) {
            return null;
        }

        $locations = array_filter(array_map(
            fn (mixed $location): ?string => is_array($location)
                ? implode(', ', array_filter([Payload::of($location)->string('city'), Payload::of($location)->string('country')])) ?: null
                : null,
            $job->array('locations'),
        ));

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $job->string('_company.name') ?? $job->string('_company.identifier') ?? '',
            description: $job->string('description') ?? '',
            applyUrl: $job->string('application_url') ?? $url,
            sourceUrl: $url,
            categoryHint: $job->string('function') ?? $job->string('department'),
            locationText: implode(' | ', $locations ?: array_filter([$job->string('city'), $job->string('country')])),
            // The widget only exposes the telecommuting flag; anything else is decided by the location text.
            workMode: $job->bool('telecommuting') === true ? WorkMode::Remote : null,
            employmentType: EmploymentType::fromLabel($job->string('employment_type')),
            postedAt: $job->date('published_on') ?? $job->date('created_at'),
        );
    }
}
