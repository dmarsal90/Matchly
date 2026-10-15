<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application;

use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Domain\Data\JobSnapshot;

final class JobSnapshotFactory
{
    public static function fromModel(JobListing $job): JobSnapshot
    {
        return new JobSnapshot(
            id: $job->id,
            title: $job->title,
            text: self::plainText($job->description),
            tags: $job->tags,
            role: $job->role,
            category: $job->category,
            seniority: $job->seniority,
            workMode: $job->work_mode,
            remoteScope: $job->remote_scope,
            allowedRegions: $job->allowed_regions,
            employmentType: $job->employment_type,
            salaryAnnualMin: $job->salary_annual_min !== null ? (float) $job->salary_annual_min : null,
            salaryAnnualMax: $job->salary_annual_max !== null ? (float) $job->salary_annual_max : null,
            salaryCurrency: $job->salary_currency,
        );
    }

    /** Sanitised HTML description → readable text with line breaks kept. */
    public static function plainText(string $html): string
    {
        $text = (string) preg_replace(['#<(br|/p|/li|/h\d|/div)\s*/?>#i', '#<li[^>]*>#i'], ["\n", "\n• "], $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace(["/[ \t]+/", "/\n{3,}/"], [' ', "\n\n"], $text));
    }
}
