<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Queue;

use App\Modules\Applications\Application\Actions\GenerateInterviewPrep;
use App\Modules\Applications\Infrastructure\Models\InterviewPrep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class GenerateInterviewPrepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public readonly int $prepId, public readonly bool $useAi)
    {
        // The person is waiting on the page.
        $this->onQueue('high');
    }

    public function handle(GenerateInterviewPrep $generate): void
    {
        $prep = InterviewPrep::query()->with('application.user', 'application.job')->find($this->prepId);

        if ($prep !== null) {
            $generate->handle($prep, $this->useAi);
        }
    }
}
