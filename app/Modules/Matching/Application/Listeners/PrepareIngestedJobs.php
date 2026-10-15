<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Listeners;

use App\Modules\Jobs\Application\Events\JobsIngested;
use App\Modules\Matching\Infrastructure\Queue\PrepareIngestedJobsJob;

final class PrepareIngestedJobs
{
    public function handle(JobsIngested $event): void
    {
        if ($event->jobIds !== [] || $event->changedJobIds !== []) {
            PrepareIngestedJobsJob::dispatch($event->jobIds, $event->changedJobIds);
        }
    }
}
