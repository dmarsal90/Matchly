<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Queries;

use App\Modules\Jobs\Domain\Services\GhostJobSignals;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Carbon\CarbonImmutable;

/** Whether an open posting shows signs of being stale, from its own age and how often it was reposted. */
final readonly class GhostJobCheck
{
    private const int SAME_WEEK = 7;

    /**
     * @return array{level: string, signals: list<string>}|null
     */
    public function for(JobListing $job): ?array
    {
        $since = $job->posted_at ?? $job->first_seen_at;
        // The same posting on another board shows up days apart at most; a repost comes weeks later.
        $others = JobListing::query()
            ->where('fingerprint', $job->fingerprint)
            ->whereKeyNot($job->id)
            ->whereNotBetween('first_seen_at', [$job->first_seen_at->subDays(self::SAME_WEEK), $job->first_seen_at->addDays(self::SAME_WEEK)])
            ->count();

        return GhostJobSignals::assess((int) $since->diffInDays(CarbonImmutable::now(), true), $others);
    }
}
