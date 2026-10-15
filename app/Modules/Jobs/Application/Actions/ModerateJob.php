<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Actions;

use App\Modules\Jobs\Application\Events\JobsIngested;
use App\Modules\Jobs\Domain\Contracts\JobRepository;
use App\Modules\Jobs\Domain\Enums\CloseReason;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Illuminate\Contracts\Events\Dispatcher;

/** Admin decision on a job held back by the suspicion heuristics. */
final readonly class ModerateJob
{
    public function __construct(private JobRepository $jobs, private Dispatcher $events) {}

    public function approve(JobListing $job): void
    {
        if ($job->status !== JobStatus::Flagged) {
            return;
        }

        $job->forceFill(['status' => JobStatus::Open, 'flag_reasons' => null])->save();

        // It was never announced while flagged: alerts and scoring pick it up now.
        $this->events->dispatch(new JobsIngested($job->source()->value('key') ?? 'moderation', [$job->id]));
    }

    public function reject(JobListing $job): void
    {
        $job->forceFill(['status' => JobStatus::Open])->save();
        $this->jobs->close($job->id, CloseReason::Moderated);
    }
}
