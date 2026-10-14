<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Queries\SkillProfileQuery;
use App\Modules\Profiles\Domain\Data\SearchProfile;
use App\Modules\Profiles\Domain\Services\SearchProfileBuilder;

/**
 * (Re)generates the suggested search profile. A profile the user edited is
 * left alone unless they ask to regenerate it.
 */
final readonly class BuildSearchProfile
{
    public function __construct(
        private SearchProfileBuilder $builder,
        private SkillProfileQuery $skills,
    ) {}

    public function handle(User $user, bool $force = false): ?SearchProfile
    {
        $profile = $user->profile()->firstOrNew();
        $current = $profile->searchProfile();

        if ($current !== null && $current->edited && ! $force) {
            return $current;
        }

        $suggested = $this->builder->build(
            targetRoles: $profile->targetRoles(),
            skills: $this->skills->for($user->id),
            workModes: $profile->workModes(),
            employmentTypes: $profile->employmentTypes(),
            salaryMin: $profile->salary_min,
            salaryCurrency: $profile->salary_currency,
            salaryPeriod: $profile->salary_period,
        );

        $profile->search_profile = $suggested->isEmpty() ? null : $suggested->toArray();
        $profile->save();

        return $suggested->isEmpty() ? null : $suggested;
    }
}
