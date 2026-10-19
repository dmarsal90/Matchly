<?php

declare(strict_types=1);

use App\Modules\Matching\Domain\Services\FitVerdict;

function verdictResult(float $ratio, bool $eligible = true, array $overrides = []): array
{
    $category = fn (float $max, bool $known = true): array => ['points' => $max * $ratio, 'max' => $max, 'ratio' => $ratio, 'known' => $known];

    return [
        'eligible' => $eligible,
        'ineligible_reasons' => $eligible ? [] : [['key' => 'residence', 'params' => []]],
        'breakdown' => ['skills' => $category(40), 'experience' => $category(20), 'role' => $category(15), 'location' => $category(10), 'languages' => $category(5), 'salary' => $category(5), 'education' => $category(5)],
        'matched_skills' => [],
        'missing_skills' => [],
        ...$overrides,
    ];
}

it('recommends applying at 4.0 or more', function (): void {
    $verdict = (new FitVerdict)->for(verdictResult(0.9));

    expect($verdict['grade'])->toBeGreaterThanOrEqual(4.0)
        ->and($verdict['recommendation'])->toBe('apply')
        ->and($verdict['dimensions'])->toHaveKeys(['match', 'north_star', 'compensation', 'culture', 'red_flags']);
});

it('advises caution between 3.0 and 4.0 and skipping below', function (): void {
    expect((new FitVerdict)->for(verdictResult(0.6))['recommendation'])->toBe('caution')
        ->and((new FitVerdict)->for(verdictResult(0.2))['recommendation'])->toBe('skip');
});

it('never recommends an ineligible job', function (): void {
    $verdict = (new FitVerdict)->for(verdictResult(1.0, eligible: false));

    expect($verdict['recommendation'])->toBe('skip')->and($verdict['flags'])->toBe(['residence']);
});

it('leaves a dimension the posting is silent about out of the average', function (): void {
    $result = verdictResult(0.9);
    $result['breakdown']['salary']['known'] = false;
    $result['breakdown']['salary']['ratio'] = 0.0;
    $verdict = (new FitVerdict)->for($result);

    expect($verdict['dimensions']['compensation'])->toBe(['grade' => null, 'known' => false])
        ->and($verdict['recommendation'])->toBe('apply');
});

it('flags a job where half the required skills are missing', function (): void {
    $required = fn (string $skill): array => ['skill' => $skill, 'name' => $skill, 'required' => true];
    $verdict = (new FitVerdict)->for(verdictResult(0.9, overrides: ['matched_skills' => [$required('php')], 'missing_skills' => [$required('go')]]));

    expect($verdict['flags'])->toBe(['missing_required'])->and($verdict['dimensions']['red_flags']['grade'])->toBe(3.4);
});
