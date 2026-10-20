<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\PuenteTalent;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Services\SalaryParser;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Support\Str;

final class PuenteTalentNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('title');

        if ($title === null) {
            return null;
        }

        // Public listing page is `<title-slug>-<displayId>`; the client is usually confidential.
        $url = 'https://puentetalent.com/jobs/'.Str::slug($title).'-'.$raw->externalId;
        $company = $job->string('hiringOrganizationName');

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company === null || strtolower($company) === 'confidential' ? 'Puente Talent (confidential client)' : $company,
            description: $job->string('descriptionHtml') ?? $job->string('descriptionText') ?? '',
            applyUrl: $url,
            sourceUrl: $url,
            categoryHint: $job->string('category'),
            locationText: $job->string('locationLabel'),
            allowedRegions: $job->strings('applicantCountries'),
            employmentType: EmploymentType::fromLabel($job->string('employmentType')),
            salary: SalaryParser::parse($job->string('salaryLabel')),
            salaryText: $job->string('salaryLabel'),
            postedAt: $job->date('createdOn'),
            remoteByDefault: true,
        );
    }
}
