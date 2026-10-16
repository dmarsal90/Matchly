<?php

declare(strict_types=1);

use App\Modules\Applications\Application\Queries\ExperimentAdvisor;

function expFigures(array $override = []): array
{
    return array_replace_recursive([
        'applied' => 20,
        'outcomes' => [],
        'timing' => ['first_response_days' => 5.0, 'first_interview_days' => 8.0],
        'by_source' => [],
        'by_category' => [],
        'calibration' => ['interview_score' => null, 'closed_score' => null, 'interview_count' => 0, 'closed_count' => 0],
    ], $override);
}

function expKeys(array $funnel): array
{
    return array_column((new ExperimentAdvisor)->advise($funnel), 'key');
}

it('suggests nothing without applications', function (): void {
    expect(expKeys(expFigures(['applied' => 0])))->toBe([]);
});

it('reads the calibration only with enough results on both sides', function (): void {
    $few = ['interview_score' => 60, 'closed_score' => 80, 'interview_count' => 2, 'closed_count' => 9];
    $low = ['interview_score' => 60, 'closed_score' => 80, 'interview_count' => 4, 'closed_count' => 9];
    $high = ['interview_score' => 85, 'closed_score' => 70, 'interview_count' => 4, 'closed_count' => 9];

    expect(expKeys(expFigures(['calibration' => $few])))->toBe([])
        ->and(expKeys(expFigures(['calibration' => $low])))->toBe(['lower_scores'])
        ->and(expKeys(expFigures(['calibration' => $high])))->toBe(['aim_high']);
});

it('points to the source or area that works better than average, never on a tiny sample', function (): void {
    $sources = [
        ['name' => 'Remotive', 'applied' => 8, 'interviews' => 4, 'offers' => 0],
        ['name' => 'Tiny', 'applied' => 2, 'interviews' => 2, 'offers' => 0],
        ['name' => 'Other', 'applied' => 10, 'interviews' => 1, 'offers' => 0],
    ];
    $advice = (new ExperimentAdvisor)->advise(expFigures(['by_source' => $sources, 'by_category' => [['name' => 'software_development', 'applied' => 6, 'interviews' => 3, 'offers' => 0]]]));

    expect(array_column($advice, 'key'))->toBe(['more_source', 'more_category'])
        ->and($advice[0]['params'])->toBe(['name' => 'Remotive', 'rate' => '50', 'applied' => '8']);
});

it('flags a source with many applications and no interview, slow answers and low volume', function (): void {
    $advice = expKeys(expFigures([
        'applied' => 6,
        'timing' => ['first_response_days' => 16.0],
        'by_source' => [['name' => 'Silent', 'applied' => 6, 'interviews' => 0, 'offers' => 0]],
    ]));

    expect($advice)->toBe(['rethink_source', 'follow_up_sooner', 'more_volume']);
});

it('gives at most four ideas', function (): void {
    $advice = expKeys(expFigures([
        'applied' => 9,
        'timing' => ['first_response_days' => 20.0],
        'by_source' => [['name' => 'A', 'applied' => 5, 'interviews' => 0, 'offers' => 0], ['name' => 'B', 'applied' => 4, 'interviews' => 3, 'offers' => 0]],
        'by_category' => [['name' => 'design', 'applied' => 4, 'interviews' => 3, 'offers' => 0]],
        'calibration' => ['interview_score' => 60, 'closed_score' => 80, 'interview_count' => 4, 'closed_count' => 9],
    ]));

    expect($advice)->toHaveCount(4);
});
