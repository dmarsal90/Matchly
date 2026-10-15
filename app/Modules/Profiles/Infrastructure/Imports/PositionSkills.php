<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Imports;

use App\Modules\Profiles\Domain\Services\EvidenceLevel;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use App\Modules\Profiles\Infrastructure\LinkedIn\LinkedInDate;
use Carbon\CarbonImmutable;

/**
 * Skills of an imported profile (LinkedIn, CV): the ones the source lists,
 * plus known skills named in job positions, which also give years of use,
 * last use and a conservative level estimate.
 */
final readonly class PositionSkills
{
    private const int MAX_SAMPLE_POSITIONS = 5;

    public function __construct(private SkillCatalog $catalog) {}

    /**
     * @param  array<string, array{name: string, evidence: array<string, mixed>}>  $listed  slug => skill the source names
     * @param  list<array{title: string, company: string|null, description?: string|null, started_on: string|null, finished_on: string|null}>  $positions
     * @param  array<string, mixed>  $defaultEvidence  evidence of skills found only in positions
     * @return list<array{skill: string, name: string, level: int|null, years: float|null, last_used_year: int|null, evidence: array<string, mixed>}>
     */
    public function combine(array $listed, array $positions, array $defaultEvidence = []): array
    {
        $skills = [];
        foreach ($listed as $slug => $skill) {
            $skills[$slug] = ['skill' => $slug, 'name' => $skill['name'], 'level' => null, 'years' => null, 'last_used_year' => null, 'evidence' => [...$skill['evidence'], 'positions' => []]];
        }

        $usage = [];
        foreach ($positions as $position) {
            $mentioned = $this->catalog->detectIn($position['title'].' '.($position['description'] ?? ''));
            if ($mentioned === []) {
                continue;
            }

            [$years, $endYear] = LinkedInDate::span($position['started_on'], $position['finished_on']);
            foreach (array_keys($mentioned) as $slug) {
                $usage[$slug]['years'] = ($usage[$slug]['years'] ?? 0.0) + $years;
                $usage[$slug]['last'] = max($usage[$slug]['last'] ?? 0, $endYear ?? 0);
                $usage[$slug]['positions'][] = trim($position['title'].($position['company'] !== null ? ' · '.$position['company'] : ''));
            }
        }

        $currentYear = CarbonImmutable::now()->year;
        foreach ($usage as $slug => $used) {
            $years = min(25.0, round($used['years'] * 2) / 2);
            $lastUsed = $used['last'] > 0 ? $used['last'] : null;

            $skills[$slug] = [
                'skill' => $slug,
                'name' => $skills[$slug]['name'] ?? $this->catalog->label($slug, $slug),
                'level' => EvidenceLevel::fromYears($years, $lastUsed, $currentYear),
                'years' => $years > 0 ? $years : null,
                'last_used_year' => $lastUsed,
                'evidence' => [
                    ...($listed[$slug]['evidence'] ?? $defaultEvidence),
                    'positions' => array_slice(array_values(array_unique($used['positions'])), 0, self::MAX_SAMPLE_POSITIONS),
                ],
            ];
        }

        return array_values($skills);
    }
}
