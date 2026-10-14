<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Data;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use DateTimeImmutable;

/**
 * The common, source-independent job model that gets persisted.
 * firstSeenAt, status and AI-extracted requirements live on the record.
 */
final readonly class JobData
{
    /**
     * @param  list<string>  $allowedRegions
     * @param  list<string>  $tags
     * @param  list<string>  $flagReasons  non-empty holds the job back for moderation
     */
    public function __construct(
        public string $source,
        public string $externalId,
        public string $title,
        public string $company,
        public string $description,
        public string $applyUrl,
        public ?string $sourceUrl,
        public ?string $companyLogoUrl,
        public ?JobCategory $category,
        public ?JobRole $role,
        public WorkMode $workMode,
        public ?EmploymentType $employmentType,
        public ?Seniority $seniority,
        public ?RemoteScope $remoteScope,
        public array $allowedRegions,
        public ?string $location,
        public ?Salary $salary,
        public ?string $salaryText,
        public array $tags,
        public ?DateTimeImmutable $postedAt,
        public array $flagReasons = [],
    ) {}

    public static function fromDraft(JobDraft $draft, Classification $classification): self
    {
        return new self(
            source: $draft->source,
            externalId: $draft->externalId,
            title: $draft->title,
            company: $draft->company,
            description: $draft->description,
            applyUrl: $draft->applyUrl,
            sourceUrl: $draft->sourceUrl,
            companyLogoUrl: $draft->companyLogoUrl,
            category: $classification->category,
            role: $classification->role,
            workMode: $classification->workMode,
            employmentType: $draft->employmentType,
            seniority: $draft->seniority ?? $classification->seniority,
            remoteScope: $classification->remoteScope,
            allowedRegions: $classification->allowedRegions,
            location: $draft->locationText,
            salary: $draft->salary,
            salaryText: $draft->salaryText,
            tags: $draft->tags,
            postedAt: $draft->postedAt,
        );
    }

    /**
     * @param  list<string>  $reasons
     */
    public function withFlagReasons(array $reasons): self
    {
        return new self(...[...get_object_vars($this), 'flagReasons' => $reasons]);
    }

    /**
     * Changes in these fields mean the posting itself changed, so derived data
     * (AI requirements, match scores) must be recomputed.
     */
    public function contentHash(): string
    {
        return hash('sha256', implode("\x1f", [
            $this->title,
            $this->company,
            $this->description,
            $this->location ?? '',
            implode(',', $this->tags),
        ]));
    }
}
