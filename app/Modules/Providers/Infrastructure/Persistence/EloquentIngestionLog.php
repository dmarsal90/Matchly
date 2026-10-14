<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Persistence;

use App\Modules\Providers\Domain\Contracts\IngestionLog;
use App\Modules\Providers\Domain\Data\IngestionReport;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Infrastructure\Models\IngestionRun;
use App\Modules\Providers\Infrastructure\Models\Source;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Throwable;

final class EloquentIngestionLog implements IngestionLog
{
    /** Back-off doubles after each consecutive failure, capped at 2^5 intervals. */
    private const int MAX_BACKOFF_EXPONENT = 5;

    public function start(ProviderDefinition $definition): int
    {
        $source = Source::forDefinition($definition);
        $source->last_fetched_at = CarbonImmutable::now();
        $source->save();

        return IngestionRun::query()->create([
            'source_id' => $source->id,
            'status' => IngestionRun::RUNNING,
            'started_at' => CarbonImmutable::now(),
        ])->id;
    }

    public function finish(int $runId, IngestionReport $report): void
    {
        $run = IngestionRun::query()->findOrFail($runId);
        $run->fill([
            'status' => $report->isPartial() ? IngestionRun::PARTIAL : IngestionRun::SUCCEEDED,
            'finished_at' => CarbonImmutable::now(),
            'fetched' => $report->fetched,
            'created' => count($report->createdIds),
            'updated' => $report->updated,
            'unchanged' => $report->unchanged,
            'skipped' => $report->skipped,
            'duplicates' => $report->duplicates,
            'flagged' => $report->flagged,
            'closed' => $report->closed,
            'failures' => $report->failures === [] ? null : array_slice($report->failures, 0, 50),
            'skip_reasons' => $report->skipReasons === [] ? null : $report->skipReasons,
        ])->save();

        Source::query()->findOrFail($run->source_id)->forceFill([
            'last_succeeded_at' => CarbonImmutable::now(),
            'consecutive_failures' => 0,
            'last_error' => $report->isPartial() ? Str::limit(implode("\n", $report->failures), 2000) : null,
        ])->save();
    }

    public function fail(int $runId, Throwable $error): void
    {
        $run = IngestionRun::query()->findOrFail($runId);
        $run->fill([
            'status' => IngestionRun::FAILED,
            'finished_at' => CarbonImmutable::now(),
            'error' => Str::limit($error->getMessage(), 2000),
        ])->save();

        $source = Source::query()->findOrFail($run->source_id);
        $source->consecutive_failures++;
        $source->last_error = Str::limit($error->getMessage(), 2000);
        $source->save();
    }

    public function nextFetchAt(string $source, int $intervalMinutes): ?DateTimeImmutable
    {
        $record = Source::query()->where('key', $source)->first();

        if ($record === null || $record->last_fetched_at === null) {
            return null;
        }

        $factor = 2 ** min($record->consecutive_failures, self::MAX_BACKOFF_EXPONENT);

        return $record->last_fetched_at->addMinutes($intervalMinutes * $factor);
    }

    public function isEnabled(string $source): bool
    {
        // Unknown sources have no admin override yet: config decides.
        return Source::query()->where('key', $source)->value('enabled') ?? true;
    }
}
