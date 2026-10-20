<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\WorkingNomads;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class WorkingNomadsNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('title');
        $company = $job->string('company_name');
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
            categoryHint: $job->string('category_name'),
            locationText: $job->string('location'),
            tags: Text::tags(array_map('trim', explode(',', $job->string('tags') ?? ''))),
            postedAt: $job->date('pub_date'),
            remoteByDefault: true,
        );
    }
}
