<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Data;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;

final readonly class ProfileData
{
    /**
     * @param  array<string, LanguageLevel>  $languages  ISO 639-1 code => level
     * @param  list<WorkMode>  $workModes
     * @param  list<EmploymentType>  $employmentTypes
     */
    public function __construct(
        public ?string $headline,
        public ?string $phone,
        public ?string $country,
        public ?string $city,
        public ?string $githubUsername,
        public ?string $linkedinUrl,
        public ?string $portfolioUrl,
        public array $languages,
        public ?int $salaryMin,
        public ?string $salaryCurrency,
        public ?SalaryPeriod $salaryPeriod,
        public array $workModes,
        public array $employmentTypes,
    ) {}
}
