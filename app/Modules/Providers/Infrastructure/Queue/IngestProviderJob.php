<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Queue;

use App\Modules\Providers\Application\Actions\IngestProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * One job per source, so a slow or failing source never delays the others.
 */
final class IngestProviderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    /** Seconds the uniqueness lock is held if the job never starts. */
    public int $uniqueFor = 3600;

    public function __construct(public readonly string $source)
    {
        $this->onQueue('ingestion');
    }

    public function uniqueId(): string
    {
        return $this->source;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("ingest:{$this->source}"))->releaseAfter(300)->expireAfter($this->timeout + 60)];
    }

    public function handle(IngestProvider $ingest, LoggerInterface $logger): void
    {
        $report = $ingest->handle($this->source);

        $logger->info('Job source ingested', [
            'source' => $this->source,
            'fetched' => $report->fetched,
            'created' => count($report->createdIds),
            'updated' => $report->updated,
            'skipped' => $report->skipped,
            'duplicates' => $report->duplicates,
            'closed' => $report->closed,
            'failures' => count($report->failures),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Job source ingestion failed', ['source' => $this->source, 'error' => $exception->getMessage()]);
    }
}
