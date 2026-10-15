<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Actions;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\CandidateProfileFactory;
use App\Modules\Matching\Application\JobSnapshotFactory;
use App\Modules\Matching\Domain\Data\CandidateProfile;
use App\Modules\Matching\Domain\Services\MatchScorer;
use App\Modules\Matching\Infrastructure\Models\JobMatch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scores visible jobs for one user (all of them, or the given ones) and
 * stores the explained results. A user without a usable profile has their
 * scores removed instead.
 */
final readonly class ScoreJobsForUser
{
    private const int CHUNK = 300;

    public function __construct(
        private CandidateProfileFactory $candidates,
        private ResolveJobRequirements $requirements,
        private MatchScorer $scorer,
    ) {}

    /**
     * @param  list<int>|null  $jobIds  null = every visible job
     * @return int jobs scored
     */
    public function handle(User $user, ?array $jobIds = null, ?CandidateProfile $candidate = null): int
    {
        $candidate ??= $this->candidates->for($user);

        if (! $candidate->isScorable()) {
            JobMatch::query()->where('user_id', $user->id)->delete();

            return 0;
        }

        $scored = 0;
        $now = CarbonImmutable::now();

        JobListing::query()
            ->visible()
            ->when($jobIds !== null, fn (Builder $query) => $query->whereKey($jobIds))
            ->chunkById(self::CHUNK, function ($jobs) use ($user, $candidate, $now, &$scored): void {
                $rows = [];
                foreach ($jobs as $job) {
                    $result = $this->scorer->score($candidate, JobSnapshotFactory::fromModel($job), $this->requirements->handle($job));
                    $rows[] = [
                        'user_id' => $user->id,
                        'job_listing_id' => $job->id,
                        'score' => $result->score,
                        'level' => $result->level,
                        'eligible' => $result->eligible,
                        'result' => json_encode($result->toArray(), JSON_THROW_ON_ERROR),
                        'computed_at' => $now,
                    ];
                }

                JobMatch::query()->upsert($rows, ['user_id', 'job_listing_id'], ['score', 'level', 'eligible', 'result', 'computed_at']);
                $scored += count($rows);
            });

        // Full rescore: drop scores of jobs that are no longer visible.
        if ($jobIds === null) {
            JobMatch::query()->where('user_id', $user->id)->where('computed_at', '<', $now)->delete();
        }

        return $scored;
    }
}
