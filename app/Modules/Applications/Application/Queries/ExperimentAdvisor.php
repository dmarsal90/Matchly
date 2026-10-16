<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Queries;

/**
 * Turns the funnel figures into a few concrete things to try next. Plain
 * rules over the person's own numbers, each with a minimum sample so a lucky
 * streak is never presented as a pattern; nothing here calls AI.
 *
 * @phpstan-type Funnel array{applied: int, outcomes: array<string, int>, timing: array{first_response_days: float|null, first_interview_days: float|null}, by_source: list<array{name: string, applied: int, interviews: int, offers: int}>, by_category: list<array{name: string, applied: int, interviews: int, offers: int}>, calibration: array{interview_score: int|null, closed_score: int|null, interview_count: int, closed_count: int}}
 */
final readonly class ExperimentAdvisor
{
    /** Applications a source or area needs before it says anything. */
    private const int MIN_GROUP = 3;

    private const int MIN_CALIBRATION = 3;

    private const int LOW_VOLUME = 10;

    private const int SLOW_DAYS = 14;

    private const int MAX = 4;

    /**
     * @param  Funnel  $funnel
     * @return list<array{key: string, params: array<string, string>}>
     */
    public function advise(array $funnel): array
    {
        if ($funnel['applied'] === 0) {
            return [];
        }

        $ideas = [];
        $overall = $this->rate($funnel['by_source'], $funnel['applied']);

        $calibration = $funnel['calibration'];
        if ($calibration['interview_score'] !== null && $calibration['closed_score'] !== null && min($calibration['interview_count'], $calibration['closed_count']) >= self::MIN_CALIBRATION) {
            $params = ['interview' => (string) $calibration['interview_score'], 'closed' => (string) $calibration['closed_score']];
            $ideas[] = $calibration['interview_score'] <= $calibration['closed_score']
                ? ['key' => 'lower_scores', 'params' => $params]
                : ['key' => 'aim_high', 'params' => $params];
        }

        foreach (['source' => $funnel['by_source'], 'category' => $funnel['by_category']] as $kind => $groups) {
            $best = $this->best($groups, $overall);
            if ($best !== null) {
                $ideas[] = ['key' => "more_{$kind}", 'params' => ['name' => $this->label($kind, $best['name']), 'rate' => (string) $this->percent($best['interviews'], $best['applied']), 'applied' => (string) $best['applied']]];
            }
        }

        $silent = array_values(array_filter($funnel['by_source'], fn (array $group): bool => $group['applied'] >= 5 && $group['interviews'] === 0));
        if ($silent !== []) {
            $ideas[] = ['key' => 'rethink_source', 'params' => ['name' => $silent[0]['name'], 'applied' => (string) $silent[0]['applied']]];
        }

        $days = $funnel['timing']['first_response_days'];
        if ($days !== null && $days >= self::SLOW_DAYS) {
            $ideas[] = ['key' => 'follow_up_sooner', 'params' => ['days' => (string) round($days)]];
        }

        if ($funnel['applied'] < self::LOW_VOLUME) {
            $ideas[] = ['key' => 'more_volume', 'params' => ['count' => (string) $funnel['applied']]];
        }

        return array_slice($ideas, 0, self::MAX);
    }

    /**
     * The group with the best interview rate that beats the overall one, with enough applications behind it.
     *
     * @param  list<array{name: string, applied: int, interviews: int, offers: int}>  $groups
     * @return array{name: string, applied: int, interviews: int, offers: int}|null
     */
    private function best(array $groups, float $overall): ?array
    {
        $eligible = array_values(array_filter($groups, fn (array $group): bool => $group['applied'] >= self::MIN_GROUP && $group['interviews'] > 0 && $overall < $group['interviews'] / $group['applied']));
        usort($eligible, fn (array $a, array $b): int => [$b['interviews'] / $b['applied'], $b['applied']] <=> [$a['interviews'] / $a['applied'], $a['applied']]);

        return $eligible[0] ?? null;
    }

    /**
     * The overall share of applications that got an interview.
     *
     * @param  list<array{name: string, applied: int, interviews: int, offers: int}>  $groups
     */
    private function rate(array $groups, int $applied): float
    {
        return $applied === 0 ? 0.0 : array_sum(array_column($groups, 'interviews')) / $applied;
    }

    private function percent(int $part, int $whole): int
    {
        return $whole === 0 ? 0 : (int) round($part / $whole * 100);
    }

    private function label(string $kind, string $name): string
    {
        if ($kind !== 'category') {
            return $name;
        }

        return $name === 'other' ? __('insights.funnel.other') : __("jobs.category.{$name}");
    }
}
