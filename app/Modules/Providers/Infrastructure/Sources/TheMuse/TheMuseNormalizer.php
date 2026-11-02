<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\TheMuse;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class TheMuseNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = Text::clean($job->string('name'));
        $company = Text::clean($job->string('company.name'));
        $url = $job->string('refs.landing_page');

        if ($title === null || $company === null || $url === null) {
            return null;
        }

        $locations = array_filter(array_map(
            static fn (mixed $location): ?string => is_array($location) ? ($location['name'] ?? null) : null,
            $job->array('locations'),
        ));

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            description: $job->string('contents') ?? '',
            applyUrl: $url,
            sourceUrl: $url,
            categoryHint: $job->string('categories.0.name'),
            locationText: implode(', ', $locations) ?: null,
            seniority: Seniority::fromLabel($job->string('levels.0.name')),
            postedAt: $job->date('publication_date'),
            remoteByDefault: true,
        );
    }
}
