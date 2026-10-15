<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Queries;

use App\Modules\Jobs\Domain\Enums\DateRange;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobGroup;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\JobSort;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use BackedEnum;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns a listing query string into filters. Permissive by design: unknown
 * or malformed values are dropped, never an error, so shared links and
 * saved alerts keep working when options change. Used by the listing and
 * by saved searches, so both always mean the same thing.
 */
final class JobFiltersParser
{
    public const array PER_PAGE_OPTIONS = [10, 20, 50];

    /** Query keys that describe a search (paging and view state left out). */
    public const array SEARCH_KEYS = [
        'q', 'work_mode', 'scope', 'type', 'role', 'category', 'seniority', 'source', 'tag',
        'posted', 'from', 'to', 'salary_min', 'currency', 'min_score',
    ];

    /**
     * @param  array<string, mixed>  $query
     * @param  int|null  $viewerId  signed-in user: enables the recommended tab, match sort and score filter
     */
    public static function parse(array $query, ?int $viewerId = null, int $defaultPerPage = 20): JobFilters
    {
        $string = fn (string $key): string => is_string($query[$key] ?? null) ? trim($query[$key]) : '';
        $perPage = is_numeric($query['per_page'] ?? null) ? (int) $query['per_page'] : $defaultPerPage;
        $recommended = $viewerId !== null && $string('tab') === 'recommended';
        $minScore = $viewerId !== null && is_numeric($query['min_score'] ?? null) ? max(0, min(100, (int) $query['min_score'])) : null;
        $currency = strtoupper($string('currency'));
        $search = (string) preg_replace('/\s+/u', ' ', $string('q'));

        return new JobFilters(
            search: $search !== '' ? mb_substr($search, 0, 200) : null,
            workModes: self::enumList($query, 'work_mode', WorkMode::class),
            remoteScopes: self::enumList($query, 'scope', RemoteScope::class),
            employmentTypes: self::enumList($query, 'type', EmploymentType::class),
            roles: self::enumList($query, 'role', JobRole::class),
            categories: self::enumList($query, 'category', JobCategory::class),
            seniorities: self::enumList($query, 'seniority', Seniority::class),
            sources: self::stringList($query, 'source', '/^[a-z0-9_-]{1,40}$/'),
            tags: array_slice(self::stringList($query, 'tag', '/^[\pL\pN .#+-]{1,40}$/u'), 0, 10),
            dateRange: DateRange::tryFrom($string('posted')),
            postedFrom: self::date($string('from')),
            postedTo: self::date($string('to')),
            salaryMin: is_numeric($query['salary_min'] ?? null) ? max(0, (int) $query['salary_min']) : null,
            salaryCurrency: preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null,
            // The recommended tab is ordered by score unless the user picks another order.
            sort: JobSort::tryFrom($string('sort')) ?? ($recommended ? JobSort::Match : JobSort::Newest),
            groupBy: JobGroup::tryFrom($string('group')),
            perPage: in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : $defaultPerPage,
            viewerId: $viewerId,
            recommended: $recommended,
            minScore: $minScore !== null && $minScore > 0 ? $minScore : null,
            showIneligible: $recommended && $string('ineligible') === '1',
        );
    }

    /**
     * Only the search-defining part of a query string, normalised: what an
     * alert stores.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    public static function searchQuery(array $query): array
    {
        $clean = [];
        foreach (self::SEARCH_KEYS as $key) {
            $value = $query[$key] ?? null;
            $value = is_array($value) ? implode(',', array_filter($value, 'is_string')) : (is_scalar($value) ? trim((string) $value) : '');
            if ($value !== '') {
                $clean[$key] = mb_substr($value, 0, 300);
            }
        }

        return $clean;
    }

    /**
     * @template T of BackedEnum
     *
     * @param  array<string, mixed>  $query
     * @param  class-string<T>  $enum
     * @return list<T>
     */
    private static function enumList(array $query, string $key, string $enum): array
    {
        return array_values(array_filter(array_map(
            fn (string $value): ?BackedEnum => $enum::tryFrom($value),
            self::stringList($query, $key, '/^[a-z_]{1,32}$/'),
        )));
    }

    /**
     * Accepts ?key=a&key=b, ?key[]=a or ?key=a,b.
     *
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private static function stringList(array $query, string $key, string $pattern): array
    {
        $raw = $query[$key] ?? null;
        $values = is_array($raw) ? $raw : (is_string($raw) ? explode(',', $raw) : []);

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $value): string => is_string($value) ? trim($value) : '', $values),
            fn (string $value): bool => preg_match($pattern, $value) === 1,
        )));
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
