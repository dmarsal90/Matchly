<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Enums\ContentSource;

final readonly class TailoredResume
{
    public function __construct(
        public ResumeData $resume,
        public ContentSource $source,
        public ?string $aiError = null,
    ) {}
}
