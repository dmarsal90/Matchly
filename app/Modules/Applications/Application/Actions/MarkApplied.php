<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use Carbon\CarbonImmutable;

/** The person tells us they sent the application on the employer's site. */
final readonly class MarkApplied
{
    public function handle(ApplicationPackage $package): void
    {
        $application = Application::query()->where('user_id', $package->user_id)->where('job_listing_id', $package->job_listing_id)->first() ?? new Application;

        if (in_array($application->status, [ApplicationStatus::Interview, ApplicationStatus::Rejected], true) && $application->exists) {
            return;
        }

        $now = CarbonImmutable::now();
        $application->forceFill([
            'user_id' => $package->user_id,
            'job_listing_id' => $package->job_listing_id,
            'status' => ApplicationStatus::Applied,
            'status_changed_at' => $now,
            'applied_at' => $application->applied_at ?? $now,
        ])->save();
    }
}
