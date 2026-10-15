<?php

declare(strict_types=1);

use App\Modules\Jobs\Application\Queries\JobFiltersParser;
use App\Modules\Jobs\Domain\Enums\JobSort;
use App\Modules\Jobs\Domain\Enums\WorkMode;

it('parses a listing query permissively', function (): void {
    $filters = JobFiltersParser::parse([
        'q' => '  vue   developer ',
        'work_mode' => 'remote,teleport',
        'tag' => ['php', '<script>'],
        'salary_min' => '50000',
        'currency' => 'eur',
        'from' => 'not a date',
        'per_page' => '999',
    ]);

    expect($filters->search)->toBe('vue developer')
        ->and($filters->workModes)->toBe([WorkMode::Remote])
        ->and($filters->tags)->toBe(['php'])
        ->and($filters->salaryMin)->toBe(50000)
        ->and($filters->salaryCurrency)->toBe('EUR')
        ->and($filters->postedFrom)->toBeNull()
        ->and($filters->perPage)->toBe(20);
});

it('enables score features only for a viewer', function (): void {
    $guest = JobFiltersParser::parse(['tab' => 'recommended', 'min_score' => '70']);
    $viewer = JobFiltersParser::parse(['tab' => 'recommended', 'min_score' => '70'], viewerId: 5);

    expect($guest->recommended)->toBeFalse()->and($guest->minScore)->toBeNull()->and($guest->sort)->toBe(JobSort::Newest)
        ->and($viewer->recommended)->toBeTrue()->and($viewer->minScore)->toBe(70)->and($viewer->sort)->toBe(JobSort::Match);
});

it('keeps only the search-defining part of a query for alerts', function (): void {
    expect(JobFiltersParser::searchQuery([
        'q' => ' laravel ',
        'work_mode' => ['remote', 'hybrid'],
        'page' => '3',
        'sort' => 'salary',
        'tab' => 'recommended',
        'min_score' => 60,
        'salary_min' => '',
    ]))->toBe(['q' => 'laravel', 'work_mode' => 'remote,hybrid', 'min_score' => '60']);
});
