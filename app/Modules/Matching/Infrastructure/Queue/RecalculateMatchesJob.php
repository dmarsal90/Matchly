<?php

declare(strict_types=1);

namespace App\Modules\Matching\Infrastructure\Queue;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Matching\Application\Actions\ScoreJobsForUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Rescores every visible job for one user. Bursts of profile changes collapse into one run. */
final class RecalculateMatchesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $userId)
    {
        $this->onQueue('matching');
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(ScoreJobsForUser $score): void
    {
        $user = User::query()->find($this->userId);

        if ($user !== null) {
            $score->handle($user);
        }
    }
}
