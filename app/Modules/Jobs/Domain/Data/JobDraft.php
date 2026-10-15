<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Data;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use DateTimeImmutable;

/**
 * What a source-specific normalizer extracts from a raw payload, before the
 * classifier decides work mode, remote scope and taxonomy. Fields the source
 * states explicitly are set; everything else stays null for the classifier.
 */
final readonly class JobDraft
{
    /**
     * @param  list<string>  $allowedRegions  explicit country names/codes or regions from the source
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $source,
        public string $externalId,
        public string $title,
        public string $company,
        public string $description,
        public string $applyUrl,
        public ?string $sourceUrl = null,
        public ?string $companyLogoUrl = null,
        public ?string $categoryHint = null,
        public ?string $locationText = null,
        public ?WorkMode $workMode = null,
        public ?RemoteScope $remoteScope = null,
        public array $allowedRegions = [],
        public ?EmploymentType $employmentType = null,
        public ?Seniority $seniority = null,
        public ?Salary $salary = null,
        public ?string $salaryText = null,
        public array $tags = [],
        public ?DateTimeImmutable $postedAt = null,
        /** Used when nothing in the posting says otherwise (true for remote-only boards). */
        public bool $remoteByDefault = false,
    ) {}

    public function withDescription(string $description): self
    {
        return new self(...[...get_object_vars($this), 'description' => $description]);
    }
}
