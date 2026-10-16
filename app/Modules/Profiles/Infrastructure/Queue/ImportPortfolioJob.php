<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Queue;

use App\Modules\Profiles\Application\Actions\ReadPortfolio;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class ImportPortfolioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    // Failures are recorded on the import; the person can simply try again.
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $importId)
    {
        // Someone is waiting on the page for the result.
        $this->onQueue('high');
    }

    public function handle(ReadPortfolio $read): void
    {
        $import = ProfileImport::query()->find($this->importId);

        if ($import !== null) {
            $read->handle($import);
        }
    }
}
