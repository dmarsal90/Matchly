<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Services\CourseCatalog;

function catalogFor(array $entries): CourseCatalog
{
    return new CourseCatalog(
        ['vue' => $entries],
        [
            ['title' => 'Videos', 'provider' => 'YouTube', 'url' => 'https://yt.example/?q={query}', 'price' => 'free'],
            ['title' => 'Courses', 'provider' => 'Udemy', 'url' => 'https://udemy.example/?q={query}', 'price' => 'paid'],
        ],
    );
}

it('lists free options before paid ones, hand-picked before searches', function (): void {
    $courses = catalogFor([
        ['Vue Mastery', 'Vue Mastery', 'https://vm.example/', 'paid'],
        ['Vue tutorial', 'vuejs.org', 'https://vuejs.org/tutorial/', 'free'],
    ])->for('vue', 'Vue.js');

    expect(array_map(fn ($course): string => $course->provider, $courses))->toBe(['vuejs.org', 'YouTube', 'Vue Mastery', 'Udemy'])
        ->and(array_map(fn ($course): string => $course->price->value, $courses))->toBe(['free', 'free', 'paid', 'paid'])
        ->and($courses[0]->curated)->toBeTrue()->and($courses[1]->curated)->toBeFalse();
});

it('builds safe search links for a skill with no hand-picked entry', function (): void {
    $courses = catalogFor([])->for('cobol', 'C++ & Co/2');

    expect($courses)->toHaveCount(2)
        ->and($courses[0]->url)->toBe('https://yt.example/?q=C%2B%2B%20%26%20Co%2F2')
        ->and($courses[0]->price->value)->toBe('free')
        ->and($courses[1]->price->value)->toBe('paid');
});
