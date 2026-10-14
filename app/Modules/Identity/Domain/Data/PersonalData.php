<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Data;

final readonly class PersonalData
{
    /**
     * @param  array<string, mixed>  $data  JSON-serializable section content
     * @param  array<string, string>  $files  archive path => file contents
     */
    public function __construct(
        public string $section,
        public array $data,
        public array $files = [],
    ) {}
}
