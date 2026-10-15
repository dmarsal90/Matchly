<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Data;

final readonly class IngestionReport
{
    /**
     * @param  list<int>  $createdIds
     * @param  list<string>  $failures  per-company or per-page problems that did not abort the run
     * @param  array<string, int>  $skipReasons  why fetched postings were not stored, for ingestion metrics
     */
    public function __construct(
        public string $source,
        public int $fetched = 0,
        public array $createdIds = [],
        public int $updated = 0,
        public int $unchanged = 0,
        public int $skipped = 0,
        public int $duplicates = 0,
        public int $flagged = 0,
        public int $closed = 0,
        public array $failures = [],
        public array $skipReasons = [],
    ) {}

    public function isPartial(): bool
    {
        return $this->failures !== [];
    }
}
