<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Queue;

use App\Modules\Applications\Application\Actions\GeneratePackage;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class GeneratePackageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public readonly int $packageId, public readonly bool $useAi)
    {
        // The person is waiting on the page.
        $this->onQueue('high');
    }

    public function handle(GeneratePackage $generate): void
    {
        $package = ApplicationPackage::query()->find($this->packageId);

        if ($package !== null) {
            $generate->handle($package, $this->useAi);
        }
    }
}
