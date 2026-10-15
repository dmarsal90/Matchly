<?php

declare(strict_types=1);

namespace App\Modules\Matching\Infrastructure\Queue;

use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\Actions\ResolveJobRequirements;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * After an ingestion: extract requirements of new/changed jobs with rules
 * (changed content invalidates earlier requirements), optionally queue the
 * AI refinement, then score the jobs for every user.
 */
final class PrepareIngestedJobsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 600;

    /**
     * @param  list<int>  $jobIds
     * @param  list<int>  $changedJobIds
     */
    public function __construct(public readonly array $jobIds, public readonly array $changedJobIds = [])
    {
        $this->onQueue('matching');
    }

    public function handle(ResolveJobRequirements $requirements): void
    {
        if ($this->changedJobIds !== []) {
            JobListing::query()->whereKey($this->changedJobIds)->update(['requirements' => null, 'requirements_extracted_at' => null]);
        }

        $ids = array_values(array_unique([...$this->jobIds, ...$this->changedJobIds]));
        $refineAll = config('ai.job_requirements') === 'all';

        JobListing::query()->whereKey($ids)->visible()->each(function (JobListing $job) use ($requirements, $refineAll): void {
            $requirements->handle($job);
            if ($refineAll) {
                RefineJobRequirementsJob::dispatch($job->id);
            }
        });

        ScoreJobsForUsersJob::dispatchForAllUsers($ids);
    }
}
