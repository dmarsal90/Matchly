<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Services;

use App\Modules\Learning\Domain\Data\Course;
use App\Modules\Learning\Domain\Enums\CoursePrice;

/**
 * Where to learn a skill, free options first and paid ones after. Hand-picked
 * resources come before search links inside each price group, and a skill
 * with no hand-picked entry still gets search links on the learning sites.
 */
final readonly class CourseCatalog
{
    /** Enough choices to decide without a wall of links. */
    private const int MAX_FREE = 4;

    private const int MAX_PAID = 3;

    /**
     * @param  array<string, list<array{0: string, 1: string, 2: string, 3: string}>>  $catalog  skill slug => [title, provider, url, price]
     * @param  list<array{title: string, provider: string, url: string, price: string}>  $search  templates with a {query} placeholder
     */
    public function __construct(private array $catalog, private array $search) {}

    /**
     * @return list<Course>
     */
    public function for(string $slug, string $name): array
    {
        $courses = [];
        foreach ($this->catalog[$slug] ?? [] as [$title, $provider, $url, $price]) {
            $courses[] = new Course($title, $provider, $url, CoursePrice::tryFrom($price) ?? CoursePrice::Paid, curated: true);
        }
        foreach ($this->search as $entry) {
            $courses[] = new Course("{$entry['title']}: {$name}", $entry['provider'], str_replace('{query}', rawurlencode($name), $entry['url']), CoursePrice::tryFrom($entry['price']) ?? CoursePrice::Paid, curated: false);
        }

        $free = array_values(array_filter($courses, fn (Course $course): bool => $course->price === CoursePrice::Free));
        $paid = array_values(array_filter($courses, fn (Course $course): bool => $course->price === CoursePrice::Paid));

        return [...array_slice($free, 0, self::MAX_FREE), ...array_slice($paid, 0, self::MAX_PAID)];
    }

    /** @return array<string, list<array{0: string, 1: string, 2: string, 3: string}>> */
    public function catalog(): array
    {
        return $this->catalog;
    }
}
