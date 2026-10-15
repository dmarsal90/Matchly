<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Arbeitnow;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class ArbeitnowNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('title');
        $company = $job->string('company_name');
        $url = $job->string('url');

        if ($title === null || $company === null || $url === null || $job->bool('remote') !== true) {
            return null;
        }

        $tags = $job->strings('tags');

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            description: $job->string('description') ?? '',
            applyUrl: $url,
            sourceUrl: $url,
            categoryHint: array_values(array_filter($tags, fn (string $tag): bool => strtolower($tag) !== 'remote'))[0] ?? null,
            locationText: $job->string('location'),
            workMode: WorkMode::Remote,
            employmentType: EmploymentType::fromLabel($job->strings('job_types')[0] ?? null),
            tags: Text::tags($tags),
            postedAt: $job->date('created_at'),
        );
    }
}
