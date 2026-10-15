<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Ats\SmartRecruiters;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;

final class SmartRecruitersNormalizer implements JobNormalizer
{
    private const array SECTIONS = ['companyDescription', 'jobDescription', 'qualifications', 'additionalInformation'];

    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = $job->string('name');
        $url = $job->string('postingUrl');

        if ($title === null || $url === null) {
            return null;
        }

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $job->string('company.name') ?? $job->string('_company.name') ?? '',
            description: $this->description($job),
            applyUrl: $job->string('applyUrl') ?? $url,
            sourceUrl: $url,
            categoryHint: $job->string('function.label') ?? $job->string('department.label'),
            locationText: $job->string('location.fullLocation') ?? implode(', ', array_filter([
                $job->string('location.city'),
                $job->string('location.country'),
            ])),
            workMode: match (true) {
                $job->bool('location.hybrid') === true => WorkMode::Hybrid,
                $job->bool('location.remote') === true => WorkMode::Remote,
                default => null,
            },
            employmentType: EmploymentType::fromLabel($job->string('typeOfEmployment.label') ?? $job->string('typeOfEmployment.id')),
            seniority: Seniority::fromLabel($job->string('experienceLevel.label')),
            postedAt: $job->date('releasedDate'),
        );
    }

    private function description(Payload $job): string
    {
        $html = '';

        foreach (self::SECTIONS as $section) {
            $text = $job->string("jobAd.sections.{$section}.text");
            if ($text !== null) {
                $html .= '<h4>'.e($job->string("jobAd.sections.{$section}.title") ?? '').'</h4>'.$text;
            }
        }

        return $html;
    }
}
