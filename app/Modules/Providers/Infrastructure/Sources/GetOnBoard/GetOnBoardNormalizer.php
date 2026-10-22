<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\GetOnBoard;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class GetOnBoardNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of(Payload::of($raw->payload)->array('attributes'));
        $links = Payload::of(Payload::of($raw->payload)->array('links'));
        $company = Payload::of(Payload::of($job->array('company'))->array('data'));
        $companyAttributes = Payload::of($company->array('attributes'));

        $title = $job->string('title');
        $companyName = $companyAttributes->string('name');
        $url = $links->string('public_url');

        if ($title === null || $companyName === null || $url === null) {
            return null;
        }

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            company: $companyName,
            description: self::description($job),
            applyUrl: $url,
            sourceUrl: $url,
            companyLogoUrl: $companyAttributes->string('logo'),
            categoryHint: $job->string('category_name'),
            locationText: implode(', ', $job->strings('countries')) ?: null,
            workMode: WorkMode::Remote,
            employmentType: EmploymentType::fromLabel(self::included($job, 'modality')),
            seniority: Seniority::fromLabel(self::included($job, 'seniority')),
            // shortcut: salary skipped, the public docs fetched do not state currency or period; map it once confirmed.
            tags: Text::tags(self::tags($job)),
            postedAt: $job->date('published_at'),
            remoteByDefault: true,
        );
    }

    private static function description(Payload $job): string
    {
        $sections = array_filter(array_map(
            fn (string $key): ?string => $job->string($key),
            ['description', 'functions', 'desirable', 'benefits'],
        ));

        return implode("\n", $sections);
    }

    private static function included(Payload $job, string $key): ?string
    {
        $data = Payload::of(Payload::of($job->array($key))->array('data'));

        return Payload::of($data->array('attributes'))->string('name');
    }

    /** @return list<string> */
    private static function tags(Payload $job): array
    {
        $tags = [];
        foreach (Payload::of($job->array('tags'))->array('data') as $tag) {
            $name = is_array($tag) ? Payload::of(Payload::of($tag)->array('attributes'))->string('name') : null;
            if ($name !== null) {
                $tags[] = $name;
            }
        }

        return $tags;
    }
}
