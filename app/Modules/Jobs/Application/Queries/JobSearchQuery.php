<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Queries;

use App\Modules\Jobs\Domain\Enums\JobGroup;
use App\Modules\Jobs\Domain\Enums\JobSort;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Jobs\Infrastructure\Search\JobTextSearch;
use App\Modules\Providers\Infrastructure\Models\Source;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;

/**
 * The public listing: visible (open, fresh, not duplicated) jobs filtered,
 * searched, grouped and sorted. Newest first by default, with the id as a
 * stable tie-breaker so pages never shuffle.
 */
final readonly class JobSearchQuery
{
    public function __construct(
        private JobTextSearch $textSearch,
        private Connection $db,
        private Cache $cache,
    ) {}

    /**
     * @return LengthAwarePaginator<int, JobListing>
     */
    public function paginate(JobFilters $filters, int $page = 1): LengthAwarePaginator
    {
        $query = $this->filtered($filters)->with('source:id,key,name,homepage_url,requires_attribution');

        if ($filters->groupBy !== null) {
            $this->orderByGroupSize($query, $filters->groupBy, $this->groupCounts($filters));
        }

        match ($filters->effectiveSort()) {
            JobSort::Relevance => $this->textSearch->orderByRelevance($query, (string) $filters->search),
            JobSort::Salary => $query->orderByRaw('COALESCE(salary_annual_max, salary_annual_min) IS NULL')
                ->orderByRaw('COALESCE(salary_annual_max, salary_annual_min) DESC'),
            JobSort::Match => $query->orderByRaw(
                'COALESCE((SELECT score FROM job_matches WHERE job_matches.job_listing_id = job_listings.id AND job_matches.user_id = ?), -1) DESC',
                [$filters->viewerId],
            ),
            JobSort::Newest => null,
        };

        return $query->latestFirst()->paginate($filters->perPage, page: $page)->withQueryString();
    }

    /**
     * Which of the given jobs match the filters (alerts check new jobs this way),
     * newest first.
     *
     * @param  list<int>  $jobIds
     * @return list<int>
     */
    public function matchingIds(JobFilters $filters, array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        return array_values(array_map(intval(...), $this->filtered($filters)->whereKey($jobIds)->latestFirst()->pluck('id')->all()));
    }

    /**
     * Totals per group over the whole filtered result (not just the page),
     * largest first. Keys are category/role values or source keys.
     *
     * @return array<string, int>
     */
    public function groupCounts(JobFilters $filters): array
    {
        if ($filters->groupBy === null) {
            return [];
        }

        $column = $filters->groupBy->column();

        /** @var array<int|string, int> $rows */
        $rows = $this->filtered($filters)
            ->toBase()
            ->select($column)
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->pluck('aggregate', $column)
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        if ($filters->groupBy === JobGroup::Source) {
            $keys = Source::query()->whereIn('id', array_keys($rows))->pluck('key', 'id');
            $mapped = [];
            foreach ($rows as $id => $count) {
                $mapped[(string) ($keys[$id] ?? $id)] = $count;
            }

            return $mapped;
        }

        $counts = [];
        foreach ($rows as $key => $count) {
            // Jobs without a category/role are grouped under an empty key.
            $counts[(string) $key] = $count;
        }

        return $counts;
    }

    /**
     * Most common tags among visible jobs, for filter suggestions.
     *
     * @return list<string>
     */
    public function popularTags(int $limit = 30): array
    {
        /** @var list<string> */
        return $this->cache->remember('jobs:popular-tags:'.$limit, 600, function () use ($limit): array {
            $visible = JobListing::query()->visible()->toBase()->select('id', 'tags');

            $tags = $this->db->getDriverName() === 'pgsql'
                ? $this->db->table($visible, 'jobs')
                    ->crossJoin($this->db->raw('LATERAL jsonb_array_elements_text(jobs.tags) AS tag(value)'))
                    ->selectRaw('tag.value AS tag, COUNT(*) AS total')
                    ->groupBy('tag.value')
                    ->orderByDesc('total')
                    ->limit($limit)
                    ->pluck('tag')
                : $this->db->table($visible, 'jobs')
                    ->crossJoin($this->db->raw('json_each(jobs.tags) AS tag'))
                    ->selectRaw('tag.value AS tag, COUNT(*) AS total')
                    ->groupBy('tag.value')
                    ->orderByDesc('total')
                    ->limit($limit)
                    ->pluck('tag');

            return array_values(array_map('strval', $tags->all()));
        });
    }

    /**
     * @return Builder<JobListing>
     */
    private function filtered(JobFilters $filters): Builder
    {
        $query = JobListing::query()->visible();

        if ($filters->hasSearch()) {
            $this->textSearch->match($query, trim((string) $filters->search));
        }

        $this->whereIn($query, 'work_mode', $filters->workModes);
        $this->whereIn($query, 'remote_scope', $filters->remoteScopes);
        $this->whereIn($query, 'employment_type', $filters->employmentTypes);
        $this->whereIn($query, 'role', $filters->roles);
        $this->whereIn($query, 'category', $filters->categories);
        $this->whereIn($query, 'seniority', $filters->seniorities);

        if ($filters->sources !== []) {
            $query->whereIn('source_id', Source::query()->whereIn('key', $filters->sources)->select('id'));
        }

        foreach ($filters->tags as $tag) {
            $query->whereJsonContains('tags', mb_strtolower($tag));
        }

        if ($filters->dateRange !== null) {
            $query->where('sort_at', '>=', CarbonImmutable::now()->subHours($filters->dateRange->hours()));
        }
        if ($filters->postedFrom !== null) {
            $query->where('sort_at', '>=', CarbonImmutable::instance($filters->postedFrom)->startOfDay());
        }
        if ($filters->postedTo !== null) {
            $query->where('sort_at', '<=', CarbonImmutable::instance($filters->postedTo)->endOfDay());
        }

        // Scores live in job_matches (one row per user and job), computed in the background.
        if ($filters->usesMatches() && ($filters->recommended || $filters->minScore !== null)) {
            $query->whereIn('id', $this->db->table('job_matches')
                ->select('job_listing_id')
                ->where('user_id', $filters->viewerId)
                ->when($filters->recommended, fn ($matches) => $matches->where('eligible', true))
                ->when($filters->minScore !== null, fn ($matches) => $matches->where('score', '>=', $filters->minScore)));
        }

        if ($filters->salaryMin !== null) {
            // Annualised figures make hourly and yearly offers comparable; currencies are not converted.
            $query->whereRaw('COALESCE(salary_annual_max, salary_annual_min) >= ?', [$filters->salaryMin]);
            if ($filters->salaryCurrency !== null) {
                $query->where('salary_currency', $filters->salaryCurrency);
            }
        }

        return $query;
    }

    /**
     * @param  Builder<JobListing>  $query
     * @param  list<BackedEnum>  $values
     */
    private function whereIn(Builder $query, string $column, array $values): void
    {
        if ($values !== []) {
            $query->whereIn($column, array_map(fn (BackedEnum $value): string|int => $value->value, $values));
        }
    }

    /**
     * Groups appear largest first; a CASE expression ranks rows by group.
     *
     * @param  Builder<JobListing>  $query
     * @param  array<string, int>  $counts
     */
    private function orderByGroupSize(Builder $query, JobGroup $group, array $counts): void
    {
        $keys = array_keys($counts);

        if ($group === JobGroup::Source) {
            $ids = Source::query()->whereIn('key', $keys)->pluck('id', 'key');
            $keys = array_map(fn (string $key): int => (int) ($ids[$key] ?? 0), $keys);
        }

        if ($keys === []) {
            return;
        }

        // Rank = position of the row's group in the ordered key list (bound, not interpolated).
        $keys = array_map(fn (string|int $key): string => (string) $key, $keys);

        if ($this->db->getDriverName() === 'pgsql') {
            $list = '{'.implode(',', array_map(fn (string $key): string => '"'.$key.'"', $keys)).'}';
            $query->orderByRaw(match ($group) {
                JobGroup::Category => "COALESCE(array_position(?::text[], COALESCE(category, '')), 2147483647)",
                JobGroup::Role => "COALESCE(array_position(?::text[], COALESCE(role, '')), 2147483647)",
                JobGroup::Source => 'COALESCE(array_position(?::text[], source_id::text), 2147483647)',
            }, [$list]);

            return;
        }

        $list = ','.implode(',', $keys).',';
        $query->orderByRaw(match ($group) {
            JobGroup::Category => "NULLIF(instr(?, ',' || COALESCE(category, '') || ','), 0) IS NULL, instr(?, ',' || COALESCE(category, '') || ',')",
            JobGroup::Role => "NULLIF(instr(?, ',' || COALESCE(role, '') || ','), 0) IS NULL, instr(?, ',' || COALESCE(role, '') || ',')",
            JobGroup::Source => "NULLIF(instr(?, ',' || source_id || ','), 0) IS NULL, instr(?, ',' || source_id || ',')",
        }, [$list, $list]);
    }
}
