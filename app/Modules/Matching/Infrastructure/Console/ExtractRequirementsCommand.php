<?php

declare(strict_types=1);

namespace App\Modules\Matching\Infrastructure\Console;

use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\Actions\ResolveJobRequirements;
use App\Modules\Matching\Infrastructure\Queue\RefineJobRequirementsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jobs:extract-requirements {--ai : Also queue the AI refinement (uses the system budget)} {--limit=500 : Jobs to process}')]
#[Description('Extract requirements of visible jobs that have none yet (rules; optionally AI)')]
final class ExtractRequirementsCommand extends Command
{
    public function handle(ResolveJobRequirements $requirements): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $count = 0;

        JobListing::query()->visible()
            ->when(! $this->option('ai'), fn ($jobs) => $jobs->whereNull('requirements'))
            ->when($this->option('ai'), fn ($jobs) => $jobs->where(fn ($pending) => $pending->whereNull('requirements')->orWhereRaw("requirements->>'source' <> 'ai'")))
            ->latestFirst()
            ->limit($limit)
            ->each(function (JobListing $job) use ($requirements, &$count): void {
                $requirements->handle($job);
                if ($this->option('ai')) {
                    RefineJobRequirementsJob::dispatch($job->id);
                }
                $count++;
            });

        $this->components->info("Processed {$count} jobs.");

        return self::SUCCESS;
    }
}
