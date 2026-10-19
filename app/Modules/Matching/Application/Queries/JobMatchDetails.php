<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Queries;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Application\Queries\GhostJobCheck;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\Actions\ResolveJobRequirements;
use App\Modules\Matching\Application\Actions\ScoreJobsForUser;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Matching\Domain\Services\FitVerdict;
use App\Modules\Matching\Infrastructure\Models\JobMatch;
use App\Modules\Matching\Infrastructure\Queue\RefineJobRequirementsJob;

/**
 * Everything the job page shows about fit: the posting's requirements and,
 * for a signed-in user, their explained score (computed on the spot when
 * the background run has not reached this job yet).
 */
final readonly class JobMatchDetails
{
    public function __construct(
        private ResolveJobRequirements $requirements,
        private ScoreJobsForUser $score,
        private FitVerdict $verdict,
        private GhostJobCheck $ghost,
    ) {}

    /**
     * @return array{requirements: array<string, mixed>, match: array<string, mixed>|null, ghost: array{level: string, signals: list<string>}|null}
     */
    public function for(JobListing $job, ?User $user): array
    {
        $requirements = $this->requirements->handle($job);

        // "lazy" AI extraction: only for postings people actually open.
        if ($job->status === JobStatus::Open && $requirements->source !== JobRequirements::SOURCE_AI && config('ai.job_requirements') === 'lazy') {
            RefineJobRequirementsJob::dispatch($job->id);
        }

        $ghost = $job->status === JobStatus::Open ? $this->ghost->for($job) : null;

        return [
            'requirements' => $requirements->toArray(),
            'match' => $user !== null ? $this->match($job, $user, $ghost !== null) : null,
            'ghost' => $ghost,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function match(JobListing $job, User $user, bool $stale): ?array
    {
        $match = JobMatch::query()->where('user_id', $user->id)->where('job_listing_id', $job->id)->first();

        if ($match === null && $job->status === JobStatus::Open) {
            $this->score->handle($user, [$job->id]);
            $match = JobMatch::query()->where('user_id', $user->id)->where('job_listing_id', $job->id)->first();
        }

        return $match !== null ? [...$match->result, 'verdict' => $this->verdict->for($match->result, $stale), 'computed_at' => $match->computed_at->toIso8601String()] : null;
    }
}
