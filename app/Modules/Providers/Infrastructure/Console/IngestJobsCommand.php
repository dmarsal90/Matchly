<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Console;

use App\Modules\Providers\Application\Actions\IngestProvider;
use App\Modules\Providers\Application\ProviderRegistry;
use App\Modules\Providers\Domain\Contracts\IngestionLog;
use App\Modules\Providers\Infrastructure\Queue\IngestProviderJob;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('jobs:ingest
    {sources?* : Source keys (default: every enabled source that is due)}
    {--force : Ignore the per-source interval and back-off}
    {--sync : Run in this process instead of queueing}')]
#[Description('Fetch job sources (queued, one job per source)')]
final class IngestJobsCommand extends Command
{
    public function handle(ProviderRegistry $registry, IngestionLog $log, IngestProvider $ingest): int
    {
        /** @var list<string> $requested */
        $requested = (array) $this->argument('sources');

        foreach (array_diff($requested, $registry->keys()) as $unknown) {
            $this->components->error("Unknown source [{$unknown}]. Available: ".implode(', ', $registry->keys()));

            return self::INVALID;
        }

        $sources = $requested !== [] ? $requested : $registry->enabled();
        $force = (bool) $this->option('force') || $requested !== [];

        foreach ($sources as $source) {
            if (! $log->isEnabled($source)) {
                $this->components->twoColumnDetail($source, '<fg=yellow>disabled by an admin</>');

                continue;
            }

            $nextAt = $log->nextFetchAt($source, $registry->intervalMinutes($source));
            if (! $force && $nextAt !== null && $nextAt > CarbonImmutable::now()) {
                $this->components->twoColumnDetail($source, "<fg=gray>not due until {$nextAt->format('H:i')}</>");

                continue;
            }

            if (! $this->option('sync')) {
                IngestProviderJob::dispatch($source);
                $this->components->twoColumnDetail($source, '<fg=green>queued</>');

                continue;
            }

            try {
                $report = $ingest->handle($source);
                $this->components->twoColumnDetail($source, sprintf(
                    '<fg=green>%d fetched · %d new · %d updated · %d skipped · %d duplicates · %d closed%s</>',
                    $report->fetched,
                    count($report->createdIds),
                    $report->updated,
                    $report->skipped,
                    $report->duplicates,
                    $report->closed,
                    $report->isPartial() ? ' · '.count($report->failures).' partial failures' : '',
                ));

                if ($report->skipReasons !== [] && $this->output->isVerbose()) {
                    $this->components->bulletList(array_map(
                        fn (string $reason, int $count): string => "skipped {$count}: {$reason}",
                        array_keys($report->skipReasons),
                        $report->skipReasons,
                    ));
                }
            } catch (Throwable $exception) {
                $this->components->twoColumnDetail($source, '<fg=red>failed: '.$exception->getMessage().'</>');
            }
        }

        return self::SUCCESS;
    }
}
