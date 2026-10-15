<?php

declare(strict_types=1);

namespace App\Modules\Matching\Domain\Data;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;

/** The job facts matching needs, independent of persistence. */
final readonly class JobSnapshot
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $allowedRegions  ISO countries and/or Region values
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $text,
        public array $tags = [],
        public ?JobRole $role = null,
        public ?JobCategory $category = null,
        public ?Seniority $seniority = null,
        public WorkMode $workMode = WorkMode::Remote,
        public ?RemoteScope $remoteScope = null,
        public array $allowedRegions = [],
        public ?EmploymentType $employmentType = null,
        public ?float $salaryAnnualMin = null,
        public ?float $salaryAnnualMax = null,
        public ?string $salaryCurrency = null,
    ) {}

    public function hasSalary(): bool
    {
        return $this->salaryAnnualMin !== null || $this->salaryAnnualMax !== null;
    }
}
