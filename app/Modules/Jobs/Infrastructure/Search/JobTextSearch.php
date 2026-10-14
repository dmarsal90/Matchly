<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * Full-text matching and ranking per database engine: PostgreSQL tsvector in
 * every real environment, LIKE for the SQLite test database. A Scout or
 * Meilisearch adapter can replace it when the catalogue grows.
 */
interface JobTextSearch
{
    /**
     * @param  Builder<\App\Modules\Jobs\Infrastructure\Models\JobListing>  $query
     */
    public function match(Builder $query, string $terms): void;

    /**
     * @param  Builder<\App\Modules\Jobs\Infrastructure\Models\JobListing>  $query
     */
    public function orderByRelevance(Builder $query, string $terms): void;
}
