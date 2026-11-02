<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Arbetsformedlingen;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class ArbetsformedlingenNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = Text::clean($job->string('headline'));
        $company = Text::clean($job->string('employer.name'));
        $sourceUrl = $job->string('webpage_url');

        if ($title === null || $company === null || $sourceUrl === null) {
            return null;
        }

        $hours = strtolower($job->string('working_hours_type.label') ?? '');
        $location = implode(', ', array_filter([$job->string('workplace_address.municipality'), $job->string('workplace_address.country')]));

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $company,
            description: $job->string('description.text_formatted') ?? '<p>'.e($job->string('description.text') ?? '').'</p>',
            applyUrl: $job->string('application_details.url') ?? $sourceUrl,
            sourceUrl: $sourceUrl,
            companyLogoUrl: $job->string('logo_url'),
            categoryHint: $job->string('occupation_field.label'),
            locationText: $location !== '' ? $location : 'Sverige',
            remoteScope: RemoteScope::CountryRestricted,
            allowedRegions: ['SE'],
            employmentType: match (true) {
                str_contains($hours, 'heltid') => EmploymentType::FullTime,
                str_contains($hours, 'deltid') => EmploymentType::PartTime,
                default => null,
            },
            postedAt: $job->date('publication_date'),
            remoteByDefault: true,
        );
    }
}
