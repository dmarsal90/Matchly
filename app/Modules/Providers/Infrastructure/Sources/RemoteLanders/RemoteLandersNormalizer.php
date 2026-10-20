<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\RemoteLanders;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Services\SalaryParser;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class RemoteLandersNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('title');
        $company = $job->string('company');
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
            categoryHint: $job->string('category'),
            locationText: $job->string('location'),
            employmentType: EmploymentType::fromLabel($job->string('type')),
            salary: SalaryParser::parse($job->string('salary')),
            salaryText: $job->string('salary'),
            tags: Text::tags($job->strings('subtags')),
            postedAt: $job->date('postedDate'),
            remoteByDefault: true,
        );
    }
}
