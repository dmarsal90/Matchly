<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * New (non-duplicate, visible) jobs landed from one source. Listeners extract
 * requirements, score them for users and match them against alerts.
 */
final readonly class JobsIngested
{
    use Dispatchable;

    /**
     * @param  list<int>  $jobIds  newly created jobs
     * @param  list<int>  $changedJobIds  existing jobs whose content changed or that reopened
     */
    public function __construct(
        public string $source,
        public array $jobIds,
        public array $changedJobIds = [],
    ) {}
}
