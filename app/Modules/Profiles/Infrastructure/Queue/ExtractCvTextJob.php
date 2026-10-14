<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Queue;

use App\Modules\Profiles\Application\Actions\ExtractCvText;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class ExtractCvTextJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly int $documentId)
    {
        $this->onQueue('default');
    }

    public function handle(ExtractCvText $extract): void
    {
        // The user may have deleted it meanwhile.
        $document = CvDocument::query()->find($this->documentId);

        if ($document !== null) {
            $extract->handle($document);
        }
    }
}
