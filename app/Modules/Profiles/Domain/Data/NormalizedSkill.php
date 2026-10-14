<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Data;

final readonly class NormalizedSkill
{
    public function __construct(
        public string $slug,
        public string $label,
        public bool $known,
    ) {}
}
