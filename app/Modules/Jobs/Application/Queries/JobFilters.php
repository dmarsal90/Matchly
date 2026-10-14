<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Queries;

use App\Modules\Jobs\Domain\Enums\DateRange;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobGroup;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\JobSort;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use DateTimeImmutable;

/**
 * Everything the listing can be filtered, sorted and grouped by. Built from
 * the query string, so every combination is a shareable URL.
 */
final readonly class JobFilters
{
    /**
     * @param  list<WorkMode>  $workModes
     * @param  list<RemoteScope>  $remoteScopes
     * @param  list<EmploymentType>  $employmentTypes
     * @param  list<JobRole>  $roles
     * @param  list<JobCategory>  $categories
     * @param  list<Seniority>  $seniorities
     * @param  list<string>  $sources  source keys
     * @param  list<string>  $tags
     */
    public function __construct(
        public ?string $search = null,
        public array $workModes = [],
        public array $remoteScopes = [],
        public array $employmentTypes = [],
        public array $roles = [],
        public array $categories = [],
        public array $seniorities = [],
        public array $sources = [],
        public array $tags = [],
        public ?DateRange $dateRange = null,
        public ?DateTimeImmutable $postedFrom = null,
        public ?DateTimeImmutable $postedTo = null,
        public ?int $salaryMin = null,
        public ?string $salaryCurrency = null,
        public JobSort $sort = JobSort::Newest,
        public ?JobGroup $groupBy = null,
        public int $perPage = 20,
    ) {}

    public function hasSearch(): bool
    {
        return $this->search !== null && trim($this->search) !== '';
    }

    /** Relevance without a query has nothing to rank by. */
    public function effectiveSort(): JobSort
    {
        return $this->sort === JobSort::Relevance && ! $this->hasSearch() ? JobSort::Newest : $this->sort;
    }
}
