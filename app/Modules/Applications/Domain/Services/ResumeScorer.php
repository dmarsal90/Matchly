<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Services;

use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Profiles\Domain\Services\SkillCatalog;

/**
 * Scores how well an applicant tracking system would take a CV, 0-100, without
 * AI: the same CV and job always give the same number. Five checks of 20 points
 * each; every check below its maximum names what to fix.
 *
 * @phpstan-type Check array{key: string, points: int, max: int, params: array<string, string>}
 */
final readonly class ResumeScorer
{
    public const int MAX = 20;

    /** Share of bullets that must carry a figure for the full "results" points. */
    private const float RESULTS_TARGET = 0.5;

    public function __construct(private SkillCatalog $catalog) {}

    /**
     * @param  bool|null  $pdfReadable  true: text and sections come through; false: nothing readable; null: some parts get lost
     * @return array{score: int, checks: list<Check>, keywords: array{matched: list<string>, missing: list<string>}}
     */
    public function score(ResumeData $resume, JobRequirements $requirements, ?bool $pdfReadable = true): array
    {
        $keywords = $this->keywords($resume, $requirements);
        $total = count($keywords['matched']) + count($keywords['missing']);

        $checks = [
            $this->sections($resume),
            $this->dates($resume),
            $this->results($resume),
            $this->check('keywords', $total === 0 ? self::MAX : (int) round(self::MAX * count($keywords['matched']) / $total), ['missing' => implode(', ', array_slice($keywords['missing'], 0, 5))]),
            $this->check('pdf', match ($pdfReadable) {
                true => self::MAX,
                null => self::MAX / 2,
                false => 0,
            }),
        ];

        return ['score' => array_sum(array_column($checks, 'points')), 'checks' => $checks, 'keywords' => $keywords];
    }

    /** @return Check */
    private function sections(ResumeData $resume): array
    {
        $present = count(array_filter([$resume->experience !== [], $resume->skills !== [], $resume->education !== []]));

        return $this->check('sections', (int) round(self::MAX * $present / 3));
    }

    /** @return Check */
    private function dates(ResumeData $resume): array
    {
        $positions = count($resume->experience);
        $dated = count(array_filter($resume->experience, fn (array $position): bool => $position['started_on'] !== null));

        return $this->check('dates', $positions === 0 ? 0 : (int) round(self::MAX * $dated / $positions), ['count' => (string) ($positions - $dated)]);
    }

    /** @return Check */
    private function results(ResumeData $resume): array
    {
        $bullets = array_merge(...array_column($resume->experience, 'bullets'));
        // shortcut: any digit counts as a result ("Laravel 11" too); tighten if scores look inflated.
        $withFigures = count(array_filter($bullets, fn (string $bullet): bool => preg_match('/\d/', $bullet) === 1));
        $share = $bullets === [] ? 0.0 : $withFigures / count($bullets);

        return $this->check('results', (int) round(self::MAX * min(1.0, $share / self::RESULTS_TARGET)));
    }

    /**
     * Required skills the CV shows, and the ones it never mentions.
     *
     * @return array{matched: list<string>, missing: list<string>}
     */
    private function keywords(ResumeData $resume, JobRequirements $requirements): array
    {
        $present = array_keys($this->catalog->detectIn($resume->plainText()));
        foreach ($resume->skills as $skill) {
            $present[] = $this->catalog->normalize($skill)->slug ?? '';
        }

        $matched = $missing = [];
        foreach ($requirements->requiredSkills as $skill) {
            if (in_array($skill['skill'], $present, true)) {
                $matched[] = $skill['name'];
            } else {
                $missing[] = $skill['name'];
            }
        }

        return ['matched' => $matched, 'missing' => $missing];
    }

    /**
     * @param  array<string, string>  $params
     * @return Check
     */
    private function check(string $key, int|float $points, array $params = []): array
    {
        return ['key' => $key, 'points' => (int) $points, 'max' => self::MAX, 'params' => $params];
    }
}
