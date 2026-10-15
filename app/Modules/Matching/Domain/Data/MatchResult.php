<?php

declare(strict_types=1);

namespace App\Modules\Matching\Domain\Data;

/**
 * An explained score: total, band, per-category breakdown, skills matched
 * and missing, eligibility and concrete suggestions. Messages are keys with
 * parameters so the UI translates them.
 */
final readonly class MatchResult
{
    public const string HIGH = 'high';

    public const string MEDIUM = 'medium';

    public const string LOW = 'low';

    /**
     * @param  list<array{key: string, params: array<string, string>}>  $ineligibleReasons
     * @param  array<string, array{points: float, max: float, ratio: float, known: bool}>  $breakdown
     * @param  list<array{skill: string, name: string, required: bool, evidenced: bool, level: int|null}>  $matchedSkills
     * @param  list<array{skill: string, name: string, required: bool}>  $missingSkills
     * @param  list<array{key: string, params: array<string, string>}>  $suggestions
     */
    public function __construct(
        public int $score,
        public string $level,
        public bool $eligible,
        public array $ineligibleReasons,
        public array $breakdown,
        public array $matchedSkills,
        public array $missingSkills,
        public array $suggestions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'level' => $this->level,
            'eligible' => $this->eligible,
            'ineligible_reasons' => $this->ineligibleReasons,
            'breakdown' => $this->breakdown,
            'matched_skills' => $this->matchedSkills,
            'missing_skills' => $this->missingSkills,
            'suggestions' => $this->suggestions,
        ];
    }

    /**
     * Keywords the job wants and the profile lacks: input for the tailored CV and letter.
     *
     * @return list<string>
     */
    public function missingKeywords(): array
    {
        return array_map(fn (array $skill): string => $skill['name'], $this->missingSkills);
    }
}
