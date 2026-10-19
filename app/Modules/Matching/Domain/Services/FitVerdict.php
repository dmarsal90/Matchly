<?php

declare(strict_types=1);

namespace App\Modules\Matching\Domain\Services;

/**
 * A 1-5 verdict in five dimensions, derived from a stored match result (no
 * extra data, no AI): match, north star, compensation, culture and red flags.
 * A dimension the posting says nothing about is unknown and stays out of the
 * average instead of dragging it down. Below the apply threshold the advice is
 * not to apply; an ineligible job is always "skip".
 */
final readonly class FitVerdict
{
    public const string APPLY = 'apply';

    public const string CAUTION = 'caution';

    public const string SKIP = 'skip';

    /** Weight of each dimension in the global grade. */
    private const array WEIGHTS = ['match' => 0.4, 'north_star' => 0.2, 'compensation' => 0.1, 'culture' => 0.1, 'red_flags' => 0.2];

    /** Match-result categories that make up each dimension. */
    private const array SOURCES = [
        'match' => ['skills', 'experience', 'languages', 'education'],
        'north_star' => ['role'],
        'compensation' => ['salary'],
        'culture' => ['location'],
    ];

    public function __construct(
        private float $applyThreshold = 4.0,
        private float $cautionThreshold = 3.0,
    ) {}

    /**
     * @param  array<string, mixed>  $result  a MatchResult::toArray()
     * @return array{grade: float, recommendation: string, dimensions: array<string, array{grade: float|null, known: bool}>, flags: list<string>}
     */
    public function for(array $result): array
    {
        /** @var array<string, array{max: float, ratio: float, known: bool}> $breakdown */
        $breakdown = (array) ($result['breakdown'] ?? []);

        $dimensions = [];
        foreach (self::SOURCES as $dimension => $categories) {
            $dimensions[$dimension] = $this->fromCategories($breakdown, $categories);
        }

        $flags = $this->flags($result);
        $dimensions['red_flags'] = ['grade' => $this->grade(max(0.0, 1.0 - 0.4 * count($flags))), 'known' => true];

        $total = 0.0;
        $weight = 0.0;
        foreach ($dimensions as $dimension => $item) {
            if ($item['grade'] !== null) {
                $total += $item['grade'] * self::WEIGHTS[$dimension];
                $weight += self::WEIGHTS[$dimension];
            }
        }
        // Red flags are always known, so the weight is never zero.
        $grade = round($total / $weight, 1);

        // An ineligible job has a hard blocker: the grade is capped under the threshold.
        $eligible = (bool) ($result['eligible'] ?? true);
        if (! $eligible) {
            $grade = min($grade, round($this->cautionThreshold - 0.1, 1));
        }

        return [
            'grade' => $grade,
            'recommendation' => match (true) {
                ! $eligible, $grade < $this->cautionThreshold => self::SKIP,
                $grade < $this->applyThreshold => self::CAUTION,
                default => self::APPLY,
            },
            'dimensions' => $dimensions,
            'flags' => $flags,
        ];
    }

    /**
     * @param  array<string, array{max: float, ratio: float, known: bool}>  $breakdown
     * @param  list<string>  $categories
     * @return array{grade: float|null, known: bool}
     */
    private function fromCategories(array $breakdown, array $categories): array
    {
        $weight = 0.0;
        $earned = 0.0;
        foreach ($categories as $category) {
            $item = $breakdown[$category] ?? null;
            if ($item !== null && $item['known']) {
                $weight += $item['max'];
                $earned += $item['max'] * $item['ratio'];
            }
        }

        return $weight > 0 ? ['grade' => $this->grade($earned / $weight), 'known' => true] : ['grade' => null, 'known' => false];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<string>
     */
    private function flags(array $result): array
    {
        $flags = array_values(array_map(fn (array $reason): string => (string) $reason['key'], (array) ($result['ineligible_reasons'] ?? [])));

        $required = array_filter((array) ($result['matched_skills'] ?? []), fn (array $skill): bool => $skill['required']);
        $missingRequired = array_filter((array) ($result['missing_skills'] ?? []), fn (array $skill): bool => $skill['required']);
        $total = count($required) + count($missingRequired);
        if ($total > 0 && count($missingRequired) / $total >= 0.5) {
            $flags[] = 'missing_required';
        }

        return $flags;
    }

    private function grade(float $ratio): float
    {
        return round(1 + 4 * max(0.0, min(1.0, $ratio)), 1);
    }
}
