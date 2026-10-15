<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Http\Requests;

use App\Modules\Jobs\Application\Queries\JobFilters;
use App\Modules\Jobs\Application\Queries\JobFiltersParser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Listing URLs get shared and bookmarked, so unknown or stale values are
 * dropped instead of failing validation: a link always shows something.
 */
final class JobSearchRequest extends FormRequest
{
    public const array PER_PAGE_OPTIONS = JobFiltersParser::PER_PAGE_OPTIONS;

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

    /**
     * @param  int|null  $viewerId  signed-in user: enables the recommended tab, match sort and score filter
     */
    public function toFilters(?int $viewerId = null): JobFilters
    {
        return JobFiltersParser::parse($this->query(), $viewerId, (int) config('jobs.per_page'));
    }

    public function page(): int
    {
        return max(1, $this->integer('page', 1));
    }
}
