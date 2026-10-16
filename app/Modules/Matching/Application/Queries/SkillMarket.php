<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Queries;

use App\Modules\Jobs\Application\Queries\SalaryBenchmark;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Profiles\Application\Queries\SkillProfileQuery;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * How the open market looks for the skills that matter to the person: theirs
 * and the ones they lack. Demand is the open listings that carry the skill as
 * a tag (the tags the sources give us), the trend compares its share of the
 * new listings of the last 30 days with the 30 before, and the pay is the
 * annualised range of the listings that state a salary. Anything with too few
 * listings behind it is left out instead of guessed.
 *
 * @phpstan-type Row array{skill: string, name: string, own: bool, jobs: int, share: float, trend: string|null, salary: array{currency: string, low: int, median: int, high: int, sample: int}|null}
 */
final readonly class SkillMarket
{
    private const int MAX_SKILLS = 20;

    private const int WINDOW_DAYS = 30;

    /** New listings a skill needs over the two windows before a trend is read. */
    private const int MIN_TREND_SAMPLE = 10;

    /** Percentage points of share that count as a change. */
    private const float TREND_THRESHOLD = 1.0;

    private const int MAX_SALARY_SAMPLE = 500;

    public function __construct(
        private SkillProfileQuery $profile,
        private SkillGapReport $gaps,
        private SkillCatalog $catalog,
        private SalaryBenchmark $salaries,
    ) {}

    /**
     * @return array{total: int, skills: list<Row>}
     */
    public function for(int $userId): array
    {
        $total = JobListing::query()->visible()->count();
        $now = CarbonImmutable::now();
        $recentAll = JobListing::query()->visible()->where('first_seen_at', '>=', $now->subDays(self::WINDOW_DAYS))->count();
        $previousAll = JobListing::query()->visible()->whereBetween('first_seen_at', [$now->subDays(2 * self::WINDOW_DAYS), $now->subDays(self::WINDOW_DAYS)])->count();

        $rows = [];
        foreach ($this->skillsOfInterest($userId) as $skill) {
            $jobs = $this->tagged($skill['skill'], $skill['name'])->count();
            if ($jobs === 0) {
                continue;
            }

            $recent = $this->tagged($skill['skill'], $skill['name'])->where('first_seen_at', '>=', $now->subDays(self::WINDOW_DAYS))->count();
            $previous = $this->tagged($skill['skill'], $skill['name'])->whereBetween('first_seen_at', [$now->subDays(2 * self::WINDOW_DAYS), $now->subDays(self::WINDOW_DAYS)])->count();

            $rows[] = [
                'skill' => $skill['skill'],
                'name' => $skill['name'],
                'own' => $skill['own'],
                'jobs' => $jobs,
                'share' => round($jobs / max(1, $total) * 100, 1),
                'trend' => $this->trend($recent, $previous, $recentAll, $previousAll),
                'salary' => $this->salaries->summarise(
                    $this->tagged($skill['skill'], $skill['name'])->whereNotNull('salary_currency')
                        ->where(fn (Builder $query) => $query->whereNotNull('salary_annual_min')->orWhereNotNull('salary_annual_max'))
                        ->limit(self::MAX_SALARY_SAMPLE)->get(['salary_currency', 'salary_annual_min', 'salary_annual_max']),
                ),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$b['jobs'], $a['name']] <=> [$a['jobs'], $b['name']]);

        return ['total' => $total, 'skills' => $rows];
    }

    /**
     * The person's own skills first, then the ones the best jobs ask for and they lack.
     *
     * @return list<array{skill: string, name: string, own: bool}>
     */
    private function skillsOfInterest(int $userId): array
    {
        $skills = [];
        foreach ($this->profile->for($userId) as $merged) {
            $skills[$merged->skill] = ['skill' => $merged->skill, 'name' => $merged->name, 'own' => true];
        }
        foreach ($this->gaps->for($userId)['skills'] as $gap) {
            $skills[$gap['skill']] ??= ['skill' => $gap['skill'], 'name' => $gap['name'], 'own' => false];
        }

        return array_slice(array_values($skills), 0, self::MAX_SKILLS);
    }

    /**
     * Open listings that carry the skill as a tag, by its slug or its name.
     *
     * @return Builder<JobListing>
     */
    private function tagged(string $slug, string $name): Builder
    {
        $names = array_unique([mb_strtolower($slug), mb_strtolower($this->catalog->label($slug, $name))]);

        return JobListing::query()->visible()->where(function (Builder $query) use ($names): void {
            foreach ($names as $tag) {
                $query->orWhereJsonContains('tags', $tag);
            }
        });
    }

    /** Up, down or flat against the whole market; null with too little to say. */
    private function trend(int $recent, int $previous, int $recentAll, int $previousAll): ?string
    {
        if ($recent + $previous < self::MIN_TREND_SAMPLE || $recentAll === 0 || $previousAll === 0) {
            return null;
        }

        $change = ($recent / $recentAll - $previous / $previousAll) * 100;

        return $change >= self::TREND_THRESHOLD ? 'up' : ($change <= -self::TREND_THRESHOLD ? 'down' : 'flat');
    }
}
