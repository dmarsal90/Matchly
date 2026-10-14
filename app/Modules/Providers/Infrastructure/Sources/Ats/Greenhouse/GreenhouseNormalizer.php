<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Ats\Greenhouse;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class GreenhouseNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('title');
        $url = $job->string('absolute_url');
        $company = $job->string('company_name') ?? $job->string('_company.name');

        if ($title === null || $url === null || $company === null) {
            return null;
        }

        $offices = array_filter(array_map(
            fn (mixed $office): ?string => is_array($office) ? Payload::of($office)->string('location') ?? Payload::of($office)->string('name') : null,
            $job->array('offices'),
        ));
        $department = $job->array('departments')[0] ?? null;

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            // The jobs endpoint returns the HTML entity-encoded.
            description: Text::decodeHtml($job->string('content') ?? ''),
            applyUrl: $url,
            sourceUrl: $url,
            categoryHint: is_array($department) ? Payload::of($department)->string('name') : null,
            locationText: implode(' | ', array_unique(array_filter([$job->string('location.name'), ...$offices]))),
            postedAt: $job->date('first_published') ?? $job->date('updated_at'),
        );
    }
}
