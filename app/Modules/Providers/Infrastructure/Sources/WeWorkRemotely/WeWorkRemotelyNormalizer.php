<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\WeWorkRemotely;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class WeWorkRemotelyNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $item = Payload::of($raw->payload);

        $rawTitle = $item->string('title');
        $url = $item->string('link') ?? $item->string('guid');

        if ($rawTitle === null || $url === null) {
            return null;
        }

        // Titles come as "Company: Job title".
        [$company, $title] = str_contains($rawTitle, ': ')
            ? array_map('trim', explode(': ', $rawTitle, 2))
            : ['', $rawTitle];

        if ($company === '' || $title === '') {
            return null;
        }

        $location = implode(', ', array_filter([$item->string('region'), $item->string('country')]));

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: Text::decodeHtml($title),
            company: Text::decodeHtml($company),
            description: $item->string('description') ?? '',
            applyUrl: $url,
            sourceUrl: $url,
            companyLogoUrl: $item->string('logo'),
            categoryHint: $item->string('category'),
            locationText: $location !== '' ? $location : null,
            workMode: WorkMode::Remote,
            employmentType: EmploymentType::fromLabel($item->string('type')),
            tags: Text::tags($item->strings('skills')),
            postedAt: $item->date('pubDate'),
            remoteByDefault: true,
        );
    }
}
