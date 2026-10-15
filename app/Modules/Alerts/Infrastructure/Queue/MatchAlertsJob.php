<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Infrastructure\Queue;

use App\Modules\Alerts\Application\Actions\MatchNewJobsToAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class MatchAlertsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  list<int>  $jobIds
     */
    public function __construct(public readonly array $jobIds)
    {
        $this->onQueue('matching');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(MatchNewJobsToAlerts $match): void
    {
        $match->handle($this->jobIds);
    }
}
