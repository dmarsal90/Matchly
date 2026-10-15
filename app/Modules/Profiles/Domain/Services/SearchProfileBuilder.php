<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Services;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Domain\Data\MergedSkill;
use App\Modules\Profiles\Domain\Data\SearchProfile;

/**
 * Suggests a search profile from the questionnaire and the merged skills.
 *
 * Roles come from the questionnaire; without them they are inferred from
 * the strongest skills (Vue + Laravel → frontend, backend → fullstack).
 * Seniority is left out on purpose: many postings do not state it, and
 * filtering on it would hide them.
 */
final readonly class SearchProfileBuilder
{
    private const int MAX_TECHNOLOGIES = 10;

    private const int MAX_INFERRED_ROLES = 3;

    public function __construct(private SkillCatalog $catalog) {}

    /**
     * @param  list<JobRole>  $targetRoles
     * @param  list<MergedSkill>  $skills  strongest first
     * @param  list<WorkMode>  $workModes
     * @param  list<EmploymentType>  $employmentTypes
     */
    public function build(
        array $targetRoles,
        array $skills,
        array $workModes,
        array $employmentTypes,
        ?int $salaryMin,
        ?string $salaryCurrency,
        ?SalaryPeriod $salaryPeriod,
    ): SearchProfile {
        // Weak, unbacked claims do not steer the search.
        $strong = array_values(array_filter(
            $skills,
            fn (MergedSkill $skill): bool => $skill->hasEvidence() || ($skill->level ?? 0) >= 3,
        ));

        return new SearchProfile(
            roles: $targetRoles !== [] ? $targetRoles : $this->inferRoles($strong),
            technologies: array_slice(array_map(fn (MergedSkill $skill): string => $skill->skill, $strong), 0, self::MAX_TECHNOLOGIES),
            seniorities: [],
            workModes: $workModes,
            employmentTypes: $employmentTypes,
            salaryMin: SearchProfile::annualSalary($salaryMin, $salaryPeriod),
            salaryCurrency: $salaryMin !== null ? $salaryCurrency : null,
        );
    }

    /**
     * @param  list<MergedSkill>  $skills
     * @return list<JobRole>
     */
    private function inferRoles(array $skills): array
    {
        $scores = [];
        foreach ($skills as $skill) {
            foreach ($this->catalog->rolesFor($skill->skill) as $role) {
                $scores[$role] = ($scores[$role] ?? 0) + $skill->confidence * ($skill->level ?? 2);
            }
        }

        // Real frontend and backend strength together means fullstack.
        $frontend = $scores[JobRole::Frontend->value] ?? 0;
        $backend = $scores[JobRole::Backend->value] ?? 0;
        if ($frontend > 0 && $backend > 0 && min($frontend, $backend) / max($frontend, $backend) >= 0.4) {
            $scores[JobRole::Fullstack->value] = ($scores[JobRole::Fullstack->value] ?? 0) + $frontend + $backend;
        }

        arsort($scores);
        $top = array_key_first($scores);
        if ($top === null) {
            return [];
        }

        // Keep roles reasonably close to the strongest one.
        $roles = [];
        foreach ($scores as $role => $score) {
            $case = JobRole::tryFrom($role);
            if ($case !== null && $score >= $scores[$top] * 0.5) {
                $roles[] = $case;
            }
        }

        return array_slice($roles, 0, self::MAX_INFERRED_ROLES);
    }
}
