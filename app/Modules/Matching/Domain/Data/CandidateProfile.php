<?php

declare(strict_types=1);

namespace App\Modules\Matching\Domain\Data;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Domain\Data\MergedSkill;
use App\Modules\Profiles\Domain\Enums\Dealbreaker;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;

/**
 * Everything about a person the scorer may use. Age, gender, nationality and
 * other sensitive attributes are not part of it, by construction: country
 * is where they work from (eligibility), never where they come from.
 */
final readonly class CandidateProfile
{
    /**
     * @param  array<string, MergedSkill>  $skills  slug => merged skill
     * @param  list<JobRole>  $roles
     * @param  list<Region>  $regions
     * @param  list<WorkMode>  $workModes
     * @param  list<EmploymentType>  $employmentTypes
     * @param  array<string, LanguageLevel>  $languages  ISO code => level
     * @param  list<Dealbreaker>  $dealbreakers
     * @param  list<string>  $degrees
     * @param  list<string>  $certifications
     */
    public function __construct(
        public int $userId,
        public array $skills = [],
        public ?int $yearsExperience = null,
        public ?Seniority $seniority = null,
        public array $roles = [],
        public ?string $country = null,
        public array $regions = [],
        public ?int $utcOffsetMin = null,
        public ?int $utcOffsetMax = null,
        public array $workModes = [],
        public array $employmentTypes = [],
        public array $languages = [],
        public ?int $salaryAnnualMin = null,
        public ?string $salaryCurrency = null,
        public array $dealbreakers = [],
        public array $degrees = [],
        public array $certifications = [],
    ) {}

    public function has(Dealbreaker $dealbreaker): bool
    {
        return in_array($dealbreaker, $this->dealbreakers, true);
    }

    /** Whether there is enough to score against at all. */
    public function isScorable(): bool
    {
        return $this->skills !== [] || $this->roles !== [];
    }
}
