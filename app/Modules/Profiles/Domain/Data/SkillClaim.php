<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Data;

use App\Modules\Profiles\Domain\Enums\SkillSource;

/** What one source says about one skill. */
final readonly class SkillClaim
{
    /**
     * @param  int|null  $level  1-5
     * @param  array<string, mixed>  $evidence  source-specific details shown to the user
     */
    public function __construct(
        public string $skill,
        public string $name,
        public SkillSource $source,
        public ?int $level = null,
        public ?float $years = null,
        public ?int $lastUsedYear = null,
        public array $evidence = [],
    ) {}
}
