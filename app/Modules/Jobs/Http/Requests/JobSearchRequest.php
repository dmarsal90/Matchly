<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Http\Requests;

use App\Modules\Jobs\Application\Queries\JobFilters;
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
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

/**
 * Listing URLs get shared and bookmarked, so unknown or stale values are
 * dropped instead of failing validation: a link always shows something.
 */
final class JobSearchRequest extends FormRequest
{
    public const array PER_PAGE_OPTIONS = [10, 20, 50];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function toFilters(): JobFilters
    {
        $perPage = $this->integer('per_page', (int) config('jobs.per_page'));

        return new JobFilters(
            search: $this->filled('q') ? $this->string('q')->squish()->toString() : null,
            workModes: $this->enumList('work_mode', WorkMode::class),
            remoteScopes: $this->enumList('scope', RemoteScope::class),
            employmentTypes: $this->enumList('type', EmploymentType::class),
            roles: $this->enumList('role', JobRole::class),
            categories: $this->enumList('category', JobCategory::class),
            seniorities: $this->enumList('seniority', Seniority::class),
            sources: $this->stringList('source', '/^[a-z0-9_-]{1,40}$/'),
            tags: array_slice($this->stringList('tag', '/^[\pL\pN .#+-]{1,40}$/u'), 0, 10),
            dateRange: DateRange::tryFrom($this->string('posted')->toString()),
            postedFrom: $this->dateParam('from'),
            postedTo: $this->dateParam('to'),
            salaryMin: $this->filled('salary_min') && is_numeric($this->input('salary_min')) ? max(0, $this->integer('salary_min')) : null,
            salaryCurrency: preg_match('/^[A-Z]{3}$/', $this->string('currency')->upper()->toString()) === 1 ? $this->string('currency')->upper()->toString() : null,
            sort: JobSort::tryFrom($this->string('sort')->toString()) ?? JobSort::Newest,
            groupBy: JobGroup::tryFrom($this->string('group')->toString()),
            perPage: in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : (int) config('jobs.per_page'),
        );
    }

    public function page(): int
    {
        return max(1, $this->integer('page', 1));
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return list<T>
     */
    private function enumList(string $key, string $enum): array
    {
        return array_values(array_filter(array_map(
            fn (string $value): ?BackedEnum => $enum::tryFrom($value),
            $this->stringList($key, '/^[a-z_]{1,32}$/'),
        )));
    }

    /**
     * Accepts ?key=a&key=b, ?key[]=a or ?key=a,b.
     *
     * @return list<string>
     */
    private function stringList(string $key, string $pattern): array
    {
        $raw = $this->query($key);
        $values = is_array($raw) ? $raw : (is_string($raw) ? explode(',', $raw) : []);

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $value): string => is_string($value) ? trim($value) : '', $values),
            fn (string $value): bool => preg_match($pattern, $value) === 1,
        )));
    }

    private function dateParam(string $key): ?CarbonImmutable
    {
        $value = $this->string($key)->toString();

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
