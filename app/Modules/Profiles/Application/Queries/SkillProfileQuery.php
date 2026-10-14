<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Queries;

use App\Modules\Profiles\Domain\Data\MergedSkill;
use App\Modules\Profiles\Domain\Services\SkillMerger;
use App\Modules\Profiles\Infrastructure\Models\UserSkill;

/** The merged skill profile of a user: every source fused, strongest first. */
final readonly class SkillProfileQuery
{
    public function __construct(private SkillMerger $merger) {}

    /**
     * @return list<MergedSkill>
     */
    public function for(int $userId): array
    {
        $claims = UserSkill::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(fn (UserSkill $skill) => $skill->toClaim());

        return $this->merger->merge($claims);
    }
}
