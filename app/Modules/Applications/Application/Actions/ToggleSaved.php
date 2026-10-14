<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Carbon\CarbonImmutable;

/**
 * The listing's star: starts tracking a job as "saved", or stops tracking it.
 */
final class ToggleSaved
{
    /**
     * @return bool whether the job is tracked afterwards
     */
    public function handle(User $user, JobListing $job): bool
    {
        $existing = Application::query()->where('user_id', $user->id)->where('job_listing_id', $job->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        $application = new Application(['status' => ApplicationStatus::Saved, 'status_changed_at' => CarbonImmutable::now()]);
        $application->user()->associate($user);
        $application->job()->associate($job);
        $application->save();

        return true;
    }
}
