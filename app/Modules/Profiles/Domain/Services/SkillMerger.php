<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Services;

use App\Modules\Profiles\Domain\Data\MergedSkill;
use App\Modules\Profiles\Domain\Data\SkillClaim;
use App\Modules\Profiles\Domain\Enums\SkillSource;

/**
 * Fuses the claims of every source into one skill profile.
 *
 * - Confidence grows with each independent source: 1 - Π(1 - weight).
 * - The user's declared level wins (they know best and can correct it), but
 *   the evidence estimate is kept alongside so the gap can be shown.
 * - Years and last use take the most favourable claim.
 */
final readonly class SkillMerger
{
    /**
     * @param  array<string, float>  $weights  source value => trust (0-1)
     */
    public function __construct(private array $weights) {}

    /**
     * @param  iterable<SkillClaim>  $claims
     * @return list<MergedSkill> strongest first
     */
    public function merge(iterable $claims): array
    {
        /** @var array<string, list<SkillClaim>> $bySkill */
        $bySkill = [];
        foreach ($claims as $claim) {
            $bySkill[$claim->skill][] = $claim;
        }

        $merged = array_map($this->mergeOne(...), array_values($bySkill));

        usort($merged, fn (MergedSkill $a, MergedSkill $b): int => [$b->confidence * ($b->level ?? 2), $b->confidence, $a->name]
            <=> [$a->confidence * ($a->level ?? 2), $a->confidence, $b->name]);

        return $merged;
    }

    /**
     * @param  list<SkillClaim>  $claims  never empty: grouped from existing claims
     */
    private function mergeOne(array $claims): MergedSkill
    {
        $sources = [];
        $declared = null;
        $evidenceLevel = null;
        $years = null;
        $lastUsed = null;
        $distrust = 1.0;

        foreach ($claims as $claim) {
            if (! in_array($claim->source, $sources, true)) {
                $sources[] = $claim->source;
                $distrust *= 1 - ($this->weights[$claim->source->value] ?? 0.5);
            }

            if ($claim->level !== null) {
                $level = max(1, min(5, $claim->level));
                if ($claim->source === SkillSource::Declared) {
                    $declared = $level;
                } else {
                    $evidenceLevel = max($evidenceLevel ?? 0, $level);
                }
            }

            if ($claim->years !== null) {
                $years = max($years ?? 0.0, $claim->years);
            }
            if ($claim->lastUsedYear !== null) {
                $lastUsed = max($lastUsed ?? 0, $claim->lastUsedYear);
            }
        }

        usort($sources, fn (SkillSource $a, SkillSource $b): int => array_search($a, SkillSource::cases(), true) <=> array_search($b, SkillSource::cases(), true));

        // The declared spelling is the user's choice; otherwise the most trusted source's.
        $name = $this->preferredName($claims);

        return new MergedSkill(
            skill: $claims[0]->skill,
            name: $name,
            sources: $sources,
            level: $declared ?? $evidenceLevel,
            declaredLevel: $declared,
            evidenceLevel: $evidenceLevel,
            years: $years,
            lastUsedYear: $lastUsed,
            confidence: round(1 - $distrust, 2),
            levelGap: $declared !== null && $evidenceLevel !== null ? $declared - $evidenceLevel : null,
        );
    }

    /**
     * @param  list<SkillClaim>  $claims
     */
    private function preferredName(array $claims): string
    {
        foreach ($claims as $claim) {
            if ($claim->source === SkillSource::Declared) {
                return $claim->name;
            }
        }

        usort($claims, fn (SkillClaim $a, SkillClaim $b): int => ($this->weights[$b->source->value] ?? 0) <=> ($this->weights[$a->source->value] ?? 0));

        return $claims[0]->name;
    }
}
