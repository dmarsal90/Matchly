<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Contracts;

use App\Modules\Jobs\Domain\Data\JobData;
use App\Modules\Jobs\Domain\Data\UpsertResult;
use App\Modules\Jobs\Domain\Enums\CloseReason;
use DateTimeImmutable;

interface JobRepository
{
    /**
     * Idempotently inserts or updates jobs from one source, keyed by
     * (source, externalId), detecting cross-source duplicates on insert.
     *
     * @param  list<JobData>  $jobs
     */
    public function upsert(string $source, array $jobs, DateTimeImmutable $seenAt): UpsertResult;

    /**
     * Closes open jobs of a source that were not seen since the given moment.
     *
     * @return int number of closed jobs
     */
    public function closeNotSeenSince(string $source, DateTimeImmutable $since, CloseReason $reason): int;

    /**
     * Closes open jobs whose publication date is older than the cutoff.
     */
    public function closeOlderThan(DateTimeImmutable $cutoff): int;

    public function close(int $jobId, CloseReason $reason): void;
}
