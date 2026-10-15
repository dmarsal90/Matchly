<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Queries;

use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Infrastructure\Models\JobMatch;

/**
 * How many open jobs the viewer's profile rules out, and why: the recommended
 * tab hides them, so it tells the user instead of silently shrinking.
 */
final class IneligibleSummary
{
    /**
     * @return array{total: int, reasons: array<string, int>}
     */
    public function for(int $userId): array
    {
        $reasons = JobMatch::query()
            ->where('user_id', $userId)
            ->where('eligible', false)
            ->whereIn('job_listing_id', JobListing::query()->visible()->select('id'))
            ->selectRaw('ineligible_reason as reason, count(*) as total')
            ->groupBy('ineligible_reason')
            ->orderByDesc('total')
            ->toBase()
            ->pluck('total', 'reason')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        return ['total' => array_sum($reasons), 'reasons' => $reasons];
    }
}
