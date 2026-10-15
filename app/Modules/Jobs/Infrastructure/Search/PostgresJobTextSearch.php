<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * websearch_to_tsquery understands what people type: quoted phrases, "or",
 * and -exclusions. The 'simple' configuration matches the generated column.
 */
final class PostgresJobTextSearch implements JobTextSearch
{
    public function match(Builder $query, string $terms): void
    {
        $query->where(function (Builder $where) use ($terms): void {
            $where->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$terms])
                // Prefix-ish fallback on the title for partial words ("larav").
                ->orWhereRaw('title ILIKE ?', ['%'.self::escapeLike($terms).'%']);
        });
    }

    public function orderByRelevance(Builder $query, string $terms): void
    {
        $query->orderByRaw("ts_rank_cd(search_vector, websearch_to_tsquery('simple', ?)) DESC", [$terms]);
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
