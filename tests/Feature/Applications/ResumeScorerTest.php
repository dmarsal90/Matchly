<?php

declare(strict_types=1);

use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Services\ResumeScorer;
use App\Modules\Matching\Domain\Data\JobRequirements;

function scorerResume(array $overrides = []): ResumeData
{
    return ResumeData::fromArray([
        'contact' => ['name' => 'Ana García'],
        'skills' => ['Laravel'],
        'education' => [['school' => 'UPV']],
        'experience' => [['title' => 'Developer', 'started_on' => '2022-01', 'bullets' => ['Cut response time by 40%', 'Led a team of 5']]],
        ...$overrides,
    ]);
}

function requiring(string ...$skills): JobRequirements
{
    return new JobRequirements(JobRequirements::SOURCE_RULES, requiredSkills: array_map(fn (string $s): array => ['skill' => $s, 'name' => ucfirst($s), 'min_years' => null], $skills));
}

it('gives a complete CV the full 100', function (): void {
    $report = app(ResumeScorer::class)->score(scorerResume(), requiring('laravel'));

    expect($report['score'])->toBe(100)
        ->and($report['keywords'])->toBe(['matched' => ['Laravel'], 'missing' => []]);
});

it('takes points off for each weakness and says what to fix', function (): void {
    $resume = scorerResume([
        'education' => [],
        'experience' => [['title' => 'Developer', 'bullets' => ['Maintained the website']]],
    ]);

    $report = app(ResumeScorer::class)->score($resume, requiring('laravel', 'redis'), false);
    $points = array_column($report['checks'], 'points', 'key');

    expect($points)->toBe(['sections' => 13, 'dates' => 0, 'results' => 0, 'keywords' => 10, 'pdf' => 0])
        ->and($report['score'])->toBe(23)
        ->and($report['keywords'])->toBe(['matched' => ['Laravel'], 'missing' => ['Redis']]);
});

it('does not penalise keywords when the job lists none, and gives half for a partly readable PDF', function (): void {
    $report = app(ResumeScorer::class)->score(scorerResume(), requiring(), null);

    expect(array_column($report['checks'], 'points', 'key'))->toMatchArray(['keywords' => 20, 'pdf' => 10]);
});
