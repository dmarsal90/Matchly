<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Console;

use App\Modules\Jobs\Application\Actions\CheckJobLinks;
use App\Modules\Jobs\Infrastructure\Queue\CheckJobLinksJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jobs:check-links {--limit=2000 : Maximum jobs to check in this round} {--sync : Check now instead of queueing}')]
#[Description('Queue apply-link checks for open jobs not verified recently; dead links close the job')]
final class CheckJobLinksCommand extends Command
{
    public function handle(CheckJobLinks $check): int
    {
        $ids = $check->due((int) $this->option('limit'));
        $batchSize = max(1, (int) config('jobs.link_check.batch_size', 200));

        if ($this->option('sync')) {
            $this->components->info('Closed '.$check->handle($ids).' of '.count($ids).' checked jobs.');

            return self::SUCCESS;
        }

        foreach (array_chunk($ids, $batchSize) as $chunk) {
            CheckJobLinksJob::dispatch($chunk);
        }

        $this->components->info('Queued '.count($ids).' link checks.');

        return self::SUCCESS;
    }
}
