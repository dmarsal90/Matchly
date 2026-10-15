<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Shared\Infrastructure\Files\EncryptedFileStore;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class DeleteCv
{
    public function __construct(private EncryptedFileStore $files, private Dispatcher $events) {}

    public function handle(CvDocument $document): void
    {
        $this->files->delete($document->path);
        $document->delete();

        if ($document->is_primary) {
            CvDocument::query()->where('user_id', $document->user_id)->latest('id')->first()?->forceFill(['is_primary' => true])->save();
        }

        $this->events->dispatch(new ProfileChanged($document->user_id));
    }
}
