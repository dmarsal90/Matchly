<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Data;

final readonly class UpsertResult
{
    /**
     * @param  list<int>  $created  ids of new, non-duplicate jobs
     * @param  list<int>  $changed  ids of existing jobs whose content changed
     */
    public function __construct(
        public array $created = [],
        public array $changed = [],
        public int $unchanged = 0,
        public int $duplicates = 0,
        public int $reopened = 0,
    ) {}

    public function merge(self $other): self
    {
        return new self(
            [...$this->created, ...$other->created],
            [...$this->changed, ...$other->changed],
            $this->unchanged + $other->unchanged,
            $this->duplicates + $other->duplicates,
            $this->reopened + $other->reopened,
        );
    }
}
