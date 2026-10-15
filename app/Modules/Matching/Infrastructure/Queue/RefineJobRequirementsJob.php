<?php

declare(strict_types=1);

namespace App\Modules\Matching\Infrastructure\Queue;

use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\Actions\RefineJobRequirements;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;

/** AI requirement extraction for one job, then a rescore of that job for everyone. */
final class RefineJobRequirementsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $jobId)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->jobId;
    }

    public function handle(RefineJobRequirements $refine, LoggerInterface $logger): void
    {
        $job = JobListing::query()->visible()->find($this->jobId);
        if ($job === null) {
            return;
        }

        try {
            $changed = $refine->handle($job);
        } catch (AiUnavailable $exception) {
            // Rules-based requirements stay in place; nothing else to do.
            $logger->info('Job requirements not refined', ['job' => $this->jobId, 'reason' => $exception->reason]);

            return;
        }

        if ($changed) {
            ScoreJobsForUsersJob::dispatchForAllUsers([$job->id]);
        }
    }
}
