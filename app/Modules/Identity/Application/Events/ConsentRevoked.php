<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Events;

use App\Modules\Identity\Domain\Enums\ConsentPurpose;

/** Modules stop the processing and delete the data obtained under this consent. */
final readonly class ConsentRevoked
{
    public function __construct(public int $userId, public ConsentPurpose $purpose) {}
}
