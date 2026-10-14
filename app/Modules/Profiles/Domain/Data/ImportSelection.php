<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Data;

/** What the user kept when reviewing an import. */
final readonly class ImportSelection
{
    /**
     * @param  list<string>  $skills  skill slugs
     * @param  array<string, list<int>>  $items  section (positions, education…) => kept indexes
     * @param  string|null  $headline  headline to apply to the profile, if any
     * @param  list<array{code: string, level: string}>  $languages  languages to add to the profile
     */
    public function __construct(
        public array $skills,
        public array $items = [],
        public ?string $headline = null,
        public array $languages = [],
    ) {}
}
