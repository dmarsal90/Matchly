<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Application\Listeners;

use App\Modules\Alerts\Infrastructure\Queue\MatchAlertsJob;
use App\Modules\Jobs\Application\Events\JobsIngested;

/** New jobs (not merely updated ones) are checked against the alerts. */
final class MatchIngestedJobsToAlerts
{
    public function handle(JobsIngested $event): void
    {
        if ($event->jobIds !== []) {
            MatchAlertsJob::dispatch($event->jobIds);
        }
    }
}
