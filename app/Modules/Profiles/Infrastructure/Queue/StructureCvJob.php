<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Queue;

use App\Modules\Profiles\Application\Actions\StructureCvWithAi;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class StructureCvJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $importId)
    {
        $this->onQueue('high');
    }

    public function handle(StructureCvWithAi $structure): void
    {
        $import = ProfileImport::query()->find($this->importId);

        if ($import !== null) {
            $structure->handle($import);
        }
    }
}
