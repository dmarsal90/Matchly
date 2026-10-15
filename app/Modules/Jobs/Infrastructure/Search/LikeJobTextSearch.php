<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Search;

use Illuminate\Database\Eloquent\Builder;

/** Portable fallback (SQLite tests): every word must appear in title, company or tags. */
final class LikeJobTextSearch implements JobTextSearch
{
    public function match(Builder $query, string $terms): void
    {
        foreach (self::words($terms) as $word) {
            $like = '%'.addcslashes($word, '\\%_').'%';
            $query->where(fn (Builder $where) => $where
                ->where('title', 'like', $like)
                ->orWhere('company', 'like', $like)
                ->orWhere('tags', 'like', $like));
        }
    }

    public function orderByRelevance(Builder $query, string $terms): void
    {
        $first = self::words($terms)[0] ?? '';
        $query->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', ['%'.addcslashes($first, '\\%_').'%']);
    }

    /**
     * @return list<string>
     */
    private static function words(string $terms): array
    {
        return array_values(array_filter(preg_split('/\s+/u', trim($terms)) ?: [], fn (string $word): bool => $word !== ''));
    }
}
