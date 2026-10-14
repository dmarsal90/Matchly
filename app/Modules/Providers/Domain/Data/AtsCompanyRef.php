<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Data;

final readonly class AtsCompanyRef
{
    public function __construct(
        public int $id,
        public string $identifier,
        public string $name,
    ) {}
}
