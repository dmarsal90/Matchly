<?php

declare(strict_types=1);

namespace App\Modules\Matching\Infrastructure\Queue;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Matching\Application\Actions\ScoreJobsForUser;
use App\Modules\Matching\Application\Queries\ScorableUsers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Scores some jobs (new or changed) for a batch of users. */
final class ScoreJobsForUsersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  list<int>  $jobIds
     * @param  list<int>  $userIds
     */
    public function __construct(public readonly array $jobIds, public readonly array $userIds)
    {
        $this->onQueue('matching');
    }

    /**
     * Splits every scorable user into batches for the given jobs.
     *
     * @param  list<int>  $jobIds
     */
    public static function dispatchForAllUsers(array $jobIds): void
    {
        if ($jobIds === []) {
            return;
        }

        ScorableUsers::query()->select('id')->chunkById((int) config('matching.users_per_batch', 50), function ($users) use ($jobIds): void {
            self::dispatch($jobIds, array_values(array_map(intval(...), $users->pluck('id')->all())));
        });
    }

    public function handle(ScoreJobsForUser $score): void
    {
        User::query()->whereKey($this->userIds)->each(fn (User $user) => $score->handle($user, $this->jobIds));
    }
}
