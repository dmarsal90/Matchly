<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Actions;

use App\Modules\Jobs\Domain\Contracts\JobRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;

/** Closes open jobs that fell out of the freshness window. */
final readonly class CloseExpiredJobs
{
    public function __construct(private JobRepository $jobs, private Config $config) {}

    public function handle(): int
    {
        return $this->jobs->closeOlderThan(
            CarbonImmutable::now()->subDays((int) $this->config->get('jobs.max_age_days')),
        );
    }
}
