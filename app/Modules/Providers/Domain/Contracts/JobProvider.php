<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Contracts;

use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;

/**
 * Adapter for one external job source. Implementations are registered in
 * config/jobs.php; adding a source never touches the ingestion pipeline.
 */
interface JobProvider
{
    public function definition(): ProviderDefinition;

    /**
     * Lazily yields every currently published posting. Throwing aborts the run;
     * recoverable problems (one ATS company down) go through ReportsPartialFailures.
     *
     * @return iterable<RawJob>
     */
    public function fetch(): iterable;
}
