<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Services;

use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Profiles\Domain\Services\SkillCatalog;

/**
 * Tailoring without AI: what the job asks for moves to the front (skills
 * and bullets) and the job keywords the CV already shows are highlighted.
 * Nothing is reworded or added.
 */
final readonly class DeterministicTailor
{
    public function __construct(private SkillCatalog $catalog) {}

    /**
     * @param  list<string>  $jobSkills  skill slugs the job asks for, most important first
     */
    public function tailor(ResumeData $master, array $jobSkills): ResumeData
    {
        $rank = array_flip($jobSkills);
        $slugOf = fn (string $name): string => $this->catalog->normalize($name)->slug ?? mb_strtolower($name);

        $skills = $master->skills;
        usort($skills, fn (string $a, string $b): int => ($rank[$slugOf($a)] ?? PHP_INT_MAX) <=> ($rank[$slugOf($b)] ?? PHP_INT_MAX));

        $experience = array_map(function (array $position) use ($rank): array {
            $relevance = fn (string $bullet): int => count(array_intersect_key($this->catalog->detectIn($bullet), $rank));
            $bullets = $position['bullets'];
            usort($bullets, fn (string $a, string $b): int => $relevance($b) <=> $relevance($a));

            return [...$position, 'bullets' => $bullets];
        }, $master->experience);

        $shown = array_keys(array_intersect_key($this->catalog->detectIn($master->plainText()), $rank));
        foreach ($master->skills as $skill) {
            if (isset($rank[$slugOf($skill)])) {
                $shown[] = $slugOf($skill);
            }
        }
        $keywords = array_values(array_unique(array_map(fn (string $slug): string => $this->catalog->label($slug, $slug), $shown)));

        return $master->with([
            'summary' => $master->summary ?? $master->headline,
            'skills' => $skills,
            'experience' => $experience,
            'keywords' => $keywords,
        ]);
    }
}
