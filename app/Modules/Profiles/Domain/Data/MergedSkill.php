<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Data;

use App\Modules\Profiles\Domain\Enums\SkillSource;

/** One skill of the final profile, combining every source that mentions it. */
final readonly class MergedSkill
{
    /**
     * @param  list<SkillSource>  $sources
     * @param  int|null  $level  the user's own level when declared, otherwise the evidence estimate
     * @param  float  $confidence  0-1, higher with more (and more reliable) sources
     * @param  int|null  $levelGap  declared minus evidence level, when both exist
     */
    public function __construct(
        public string $skill,
        public string $name,
        public array $sources,
        public ?int $level,
        public ?int $declaredLevel,
        public ?int $evidenceLevel,
        public ?float $years,
        public ?int $lastUsedYear,
        public float $confidence,
        public ?int $levelGap,
    ) {}

    public function hasEvidence(): bool
    {
        foreach ($this->sources as $source) {
            if ($source->isEvidence()) {
                return true;
            }
        }

        return false;
    }

    public function isDeclared(): bool
    {
        return in_array(SkillSource::Declared, $this->sources, true);
    }

    /**
     * @return array{skill: string, name: string, sources: list<string>, level: int|null, declared_level: int|null, evidence_level: int|null, years: float|null, last_used_year: int|null, confidence: float, level_gap: int|null, has_evidence: bool}
     */
    public function toArray(): array
    {
        return [
            'skill' => $this->skill,
            'name' => $this->name,
            'sources' => array_map(fn (SkillSource $source): string => $source->value, $this->sources),
            'level' => $this->level,
            'declared_level' => $this->declaredLevel,
            'evidence_level' => $this->evidenceLevel,
            'years' => $this->years,
            'last_used_year' => $this->lastUsedYear,
            'confidence' => $this->confidence,
            'level_gap' => $this->levelGap,
            'has_evidence' => $this->hasEvidence(),
        ];
    }
}
