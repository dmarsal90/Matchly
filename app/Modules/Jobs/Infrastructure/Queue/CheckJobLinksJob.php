<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Queue;

use App\Modules\Jobs\Application\Actions\CheckJobLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class CheckJobLinksJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    /**
     * @param  list<int>  $jobIds
     */
    public function __construct(public readonly array $jobIds)
    {
        $this->onQueue('maintenance');
    }

    public function handle(CheckJobLinks $check): void
    {
        $check->handle($this->jobIds);
    }
}
