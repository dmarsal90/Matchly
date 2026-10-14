<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Actions;

use App\Modules\Jobs\Domain\Contracts\JobRepository;
use App\Modules\Jobs\Domain\Contracts\LinkChecker;
use App\Modules\Jobs\Domain\Enums\CloseReason;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Re-checks the apply link of open jobs and closes those that are gone.
 */
final readonly class CheckJobLinks
{
    public function __construct(
        private LinkChecker $checker,
        private JobRepository $jobs,
        private Config $config,
    ) {}

    /**
     * Ids of open jobs not checked recently, oldest check first.
     *
     * @return list<int>
     */
    public function due(int $limit): array
    {
        $threshold = CarbonImmutable::now()->subDays((int) $this->config->get('jobs.link_check.interval_days', 3));

        /** @var list<int> */
        return JobListing::query()
            ->where('status', JobStatus::Open)
            ->whereNull('duplicate_of_id')
            ->where(fn ($query) => $query->whereNull('link_checked_at')->orWhere('link_checked_at', '<', $threshold))
            ->orderByRaw('link_checked_at IS NOT NULL, link_checked_at')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<int>  $jobIds
     * @return int closed jobs
     */
    public function handle(array $jobIds): int
    {
        $closed = 0;

        JobListing::query()
            ->whereKey($jobIds)
            ->where('status', JobStatus::Open)
            ->select(['id', 'apply_url'])
            ->each(function (JobListing $job) use (&$closed): void {
                if ($this->checker->isDead($job->apply_url)) {
                    $this->jobs->close($job->id, CloseReason::LinkDead);
                    $closed++;
                }

                JobListing::query()->whereKey($job->id)->update(['link_checked_at' => CarbonImmutable::now()]);
            });

        return $closed;
    }
}
