<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Actions;

use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\JobSnapshotFactory;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Matching\Domain\Services\RuleBasedRequirementExtractor;
use Carbon\CarbonImmutable;

/**
 * Requirements of a job, computed once: stored ones are reused; otherwise
 * the rule-based extraction runs and is saved (the AI may refine it later).
 */
final readonly class ResolveJobRequirements
{
    public function __construct(private RuleBasedRequirementExtractor $extractor) {}

    public function handle(JobListing $job): JobRequirements
    {
        if ($job->requirements !== null) {
            return JobRequirements::fromArray($job->requirements);
        }

        $requirements = $this->extractor->extract(JobSnapshotFactory::fromModel($job));

        $job->forceFill(['requirements' => $requirements->toArray(), 'requirements_extracted_at' => CarbonImmutable::now()])->saveQuietly();

        return $requirements;
    }
}
