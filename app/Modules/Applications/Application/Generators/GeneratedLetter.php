<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\Applications\Domain\Enums\ContentSource;

final readonly class GeneratedLetter
{
    /**
     * @param  list<array{key: string, params: array<string, string>}>  $warnings
     */
    public function __construct(
        public string $text,
        public ContentSource $source,
        public array $warnings = [],
        public ?string $aiError = null,
    ) {}
}
