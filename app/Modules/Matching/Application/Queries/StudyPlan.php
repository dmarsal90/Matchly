<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Queries;

use Carbon\CarbonImmutable;

/**
 * Spreads the study hours of the skills the person lacks over four weeks, in
 * the order of the skill-gap ranking and within the hours they say they have
 * per week. The hours are the rough estimates of config/courses.php, so the
 * plan is a starting point to adjust, never a promise.
 *
 * @phpstan-type Course array{title: string, url: string}
 * @phpstan-type Item array{skill: string, name: string, hours: int, course: Course|null, part: int, parts: int, continues: bool}
 * @phpstan-type Week array{week: int, starts_on: string, total: int, items: list<Item>}
 */
final readonly class StudyPlan
{
    public const int WEEKS = 4;

    public const int MIN_HOURS = 1;

    public const int MAX_HOURS = 40;

    public const int DEFAULT_HOURS = 5;

    /**
     * @param  list<array{skill: string, name: string, hours: int, courses: list<array{title: string, url: string, price: string, curated: bool}>}>  $skills  best first
     * @return array{hours_per_week: int, weeks: list<Week>, continues: list<array{name: string, hours: int}>, left_out: list<string>}
     */
    public function build(array $skills, int $hoursPerWeek, ?CarbonImmutable $from = null): array
    {
        $hoursPerWeek = max(self::MIN_HOURS, min(self::MAX_HOURS, $hoursPerWeek));
        $start = $this->firstMonday($from ?? CarbonImmutable::now());

        /** @var array<int, int> $totals hours already planned in each week */
        $totals = array_fill(0, self::WEEKS, 0);
        /** @var array<int, list<Item>> $items */
        $items = array_fill(0, self::WEEKS, []);

        $leftOut = [];
        $continues = [];
        $week = 0;
        foreach ($skills as $skill) {
            $remaining = max(1, $skill['hours']);
            /** @var list<array{week: int, hours: int}> $pieces */
            $pieces = [];
            $cursor = $week;
            $taken = $totals;

            while ($remaining > 0 && $cursor < self::WEEKS) {
                $room = $hoursPerWeek - $taken[$cursor];
                if ($room <= 0) {
                    $cursor++;

                    continue;
                }
                $take = min($room, $remaining);
                $pieces[] = ['week' => $cursor, 'hours' => $take];
                $taken[$cursor] += $take;
                $remaining -= $take;
            }

            // No room at all: it is named, not squeezed in. Room for part of it: the part that fits is
            // planned and the rest is said to go on after the fourth week.
            if ($pieces === []) {
                $leftOut[] = $skill['name'];

                continue;
            }
            if ($remaining > 0) {
                $continues[] = ['name' => $skill['name'], 'hours' => $remaining];
            }

            $free = array_values(array_filter($skill['courses'], fn (array $course): bool => $course['price'] === 'free' && $course['curated']));
            $course = isset($free[0]) ? ['title' => $free[0]['title'], 'url' => $free[0]['url']] : null;
            foreach ($pieces as $index => $piece) {
                $items[$piece['week']][] = ['skill' => $skill['skill'], 'name' => $skill['name'], 'hours' => $piece['hours'], 'course' => $course, 'part' => $index + 1, 'parts' => count($pieces), 'continues' => $remaining > 0 && $index === count($pieces) - 1];
            }
            $totals = $taken;
            $week = min($cursor, self::WEEKS - 1);
        }

        $weeks = [];
        for ($index = 0; $index < self::WEEKS; $index++) {
            $weeks[] = ['week' => $index + 1, 'starts_on' => $start->addWeeks($index)->toDateString(), 'total' => $totals[$index], 'items' => $items[$index]];
        }

        return ['hours_per_week' => $hoursPerWeek, 'weeks' => $weeks, 'continues' => $continues, 'left_out' => $leftOut];
    }

    private function firstMonday(CarbonImmutable $from): CarbonImmutable
    {
        $day = $from->startOfDay();

        return $day->isMonday() ? $day : $day->next(CarbonImmutable::MONDAY);
    }
}
