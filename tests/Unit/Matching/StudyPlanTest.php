<?php

declare(strict_types=1);

use App\Modules\Matching\Application\Queries\StudyPlan;
use App\Modules\Shared\Domain\Services\IcsCalendar;
use Carbon\CarbonImmutable;

function planSkill(string $slug, int $hours, bool $free = true): array
{
    return ['skill' => $slug, 'name' => ucfirst($slug), 'hours' => $hours, 'courses' => $free ? [['title' => "{$slug} course", 'url' => "https://example.com/{$slug}", 'price' => 'free', 'curated' => true]] : []];
}

it('starts on the next Monday and fills the weeks in ranking order within the weekly hours', function (): void {
    $plan = (new StudyPlan)->build([planSkill('php', 12), planSkill('docker', 6)], 8, CarbonImmutable::parse('2026-10-16'));

    expect(array_column($plan['weeks'], 'starts_on'))->toBe(['2026-10-19', '2026-10-26', '2026-11-02', '2026-11-09'])
        ->and(array_column($plan['weeks'], 'total'))->toBe([8, 8, 2, 0])
        ->and($plan['weeks'][0]['items'])->toHaveCount(1)
        ->and($plan['weeks'][0]['items'][0])->toMatchArray(['skill' => 'php', 'hours' => 8, 'part' => 1, 'parts' => 2])
        // The second skill takes the room left in week two, then goes on to week three.
        ->and(array_map(fn (array $item): string => "{$item['skill']}:{$item['hours']}", $plan['weeks'][1]['items']))->toBe(['php:4', 'docker:4'])
        ->and($plan['weeks'][2]['items'][0])->toMatchArray(['skill' => 'docker', 'hours' => 2, 'part' => 2, 'parts' => 2])
        ->and($plan['left_out'])->toBe([]);
});

it('keeps a Monday as the start and clamps the weekly hours', function (): void {
    $plan = (new StudyPlan)->build([planSkill('go', 1)], 500, CarbonImmutable::parse('2026-10-19'));

    expect($plan['weeks'][0]['starts_on'])->toBe('2026-10-19')
        ->and($plan['hours_per_week'])->toBe(40)
        ->and((new StudyPlan)->build([], 0)['hours_per_week'])->toBe(1);
});

it('plans the part of a big skill that fits and says the rest goes on, then names what never started', function (): void {
    $plan = (new StudyPlan)->build([planSkill('rust', 30), planSkill('kotlin', 60), planSkill('git', 5)], 10, CarbonImmutable::parse('2026-10-19'));

    // Rust takes three weeks; Kotlin gets the 10 hours of week four and has 50 left; Git has no room.
    expect(array_column($plan['weeks'], 'total'))->toBe([10, 10, 10, 10])
        ->and($plan['continues'])->toBe([['name' => 'Kotlin', 'hours' => 50]])
        ->and($plan['left_out'])->toBe(['Git'])
        ->and($plan['weeks'][3]['items'][0])->toMatchArray(['skill' => 'kotlin', 'hours' => 10, 'continues' => true])
        ->and($plan['weeks'][0]['items'][0]['continues'])->toBeFalse();
});

it('fills the default pace with a language that does not fit in four weeks', function (): void {
    $plan = (new StudyPlan)->build([planSkill('php', 60)], StudyPlan::DEFAULT_HOURS, CarbonImmutable::parse('2026-10-19'));

    expect(array_sum(array_column($plan['weeks'], 'total')))->toBe(20)
        ->and($plan['continues'])->toBe([['name' => 'Php', 'hours' => 40]]);
});

it('offers only curated free courses', function (): void {
    $plan = (new StudyPlan)->build([planSkill('nocourse', 3, false), planSkill('yes', 3)], 10, CarbonImmutable::parse('2026-10-19'));
    $items = $plan['weeks'][0]['items'];

    expect($items[0]['course'])->toBeNull()
        ->and($items[1]['course'])->toBe(['title' => 'yes course', 'url' => 'https://example.com/yes']);
});

it('writes a valid calendar: CRLF lines, escaped text, folded long lines, all-day events', function (): void {
    $ics = (new IcsCalendar('Acme', new DateTimeImmutable('2026-10-16 09:00:00 UTC')))
        ->allDay('uid-1@example.com', new DateTimeImmutable('2026-10-19'), 'Study PHP, Laravel; part 1', "Line one\nLine two ".str_repeat('é', 60), 'https://example.com/php')
        ->render();

    expect($ics)->toStartWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n")->toEndWith("END:VCALENDAR\r\n")
        ->and($ics)->toContain("DTSTART;VALUE=DATE:20261019\r\n")->toContain("DTEND;VALUE=DATE:20261020\r\n")
        ->toContain('SUMMARY:Study PHP\, Laravel\; part 1')->toContain('Line one\nLine two')
        ->toContain("DTSTAMP:20261016T090000Z\r\n")->toContain("URL:https://example.com/php\r\n");
    foreach (explode("\r\n", rtrim($ics)) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75)->and(mb_check_encoding($line, 'UTF-8'))->toBeTrue();
    }
});
