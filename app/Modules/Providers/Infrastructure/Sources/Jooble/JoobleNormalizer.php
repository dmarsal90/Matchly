<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Jooble;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Services\SalaryParser;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class JoobleNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = Text::clean($job->string('title'));
        $company = Text::clean($job->string('company'));
        $url = $job->string('link');

        if ($title === null || $company === null || $url === null) {
            return null;
        }

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            description: '<p>'.e(Text::clean($job->string('snippet')) ?? '').'</p>',
            applyUrl: $url,
            sourceUrl: $url,
            locationText: $job->string('location'),
            employmentType: EmploymentType::fromLabel($job->string('type')),
            salary: SalaryParser::parse($job->string('salary')),
            salaryText: $job->string('salary'),
            postedAt: $job->date('updated'),
            remoteByDefault: true,
        );
    }
}
