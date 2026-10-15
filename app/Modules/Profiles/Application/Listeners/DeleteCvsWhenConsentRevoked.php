<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Listeners;

use App\Modules\Identity\Application\Events\ConsentRevoked;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Profiles\Application\Actions\DeleteCv;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;

final readonly class DeleteCvsWhenConsentRevoked
{
    public function __construct(private DeleteCv $deleteCv) {}

    public function handle(ConsentRevoked $event): void
    {
        if ($event->purpose !== ConsentPurpose::CvProcessing) {
            return;
        }

        CvDocument::query()->where('user_id', $event->userId)->each(fn (CvDocument $document) => $this->deleteCv->handle($document));
    }
}
