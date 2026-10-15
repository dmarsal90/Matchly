<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Console;

use App\Modules\Jobs\Application\Actions\CloseExpiredJobs;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jobs:close-expired')]
#[Description('Close open jobs older than the freshness window (jobs.max_age_days)')]
final class CloseExpiredJobsCommand extends Command
{
    public function handle(CloseExpiredJobs $close): int
    {
        $this->components->info("Closed {$close->handle()} expired jobs.");

        return self::SUCCESS;
    }
}
