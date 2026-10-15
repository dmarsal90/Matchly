<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Listeners;

use App\Modules\Identity\Application\Events\ConsentRevoked;
use App\Modules\Profiles\Application\Actions\DeleteSourceData;
use App\Modules\Profiles\Domain\Enums\SkillSource;

/** Withdrawing consent for a source deletes what was obtained through it (GDPR art. 7.3). */
final readonly class DeleteImportedDataWhenConsentRevoked
{
    public function __construct(private DeleteSourceData $deleteSourceData) {}

    public function handle(ConsentRevoked $event): void
    {
        $source = SkillSource::forConsent($event->purpose);

        if ($source !== null) {
            $this->deleteSourceData->handle($event->userId, $source);
        }
    }
}
