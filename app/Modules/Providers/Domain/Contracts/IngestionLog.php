<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Contracts;

use App\Modules\Providers\Domain\Data\IngestionReport;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use DateTimeImmutable;
use Throwable;

/** Run bookkeeping per source: history, metrics and back-off after failures. */
interface IngestionLog
{
    public function start(ProviderDefinition $definition): int;

    public function finish(int $runId, IngestionReport $report): void;

    public function fail(int $runId, Throwable $error): void;

    /**
     * When the source may be fetched again, given its interval and recent failures.
     */
    public function nextFetchAt(string $source, int $intervalMinutes): ?DateTimeImmutable;

    public function isEnabled(string $source): bool;
}
