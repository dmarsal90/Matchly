<?php

declare(strict_types=1);

namespace App\Modules\AI\Application\Queries;

use App\Modules\AI\Application\BudgetGuard;
use App\Modules\AI\Infrastructure\Models\AiUsage;
use Carbon\CarbonImmutable;

/** This month's AI usage of a user, for their settings page. */
final readonly class AiUsageSummary
{
    public function __construct(private BudgetGuard $budget) {}

    /**
     * @return array{calls: int, cost_usd: float, own_key_cost_usd: float, budget_usd: float, budget_used_usd: float, by_task: array<string, int>}
     */
    public function forUser(int $userId): array
    {
        $usages = AiUsage::query()
            ->where('user_id', $userId)
            ->where('success', true)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->get(['task', 'key_owner', 'cost_usd']);

        return [
            'calls' => $usages->count(),
            'cost_usd' => round((float) $usages->sum('cost_usd'), 4),
            'own_key_cost_usd' => round((float) $usages->where('key_owner', AiUsage::USER_KEY)->sum('cost_usd'), 4),
            'budget_usd' => $this->budget->limit($userId),
            'budget_used_usd' => round($this->budget->spent($userId), 4),
            'by_task' => $usages->countBy('task')->map(fn (mixed $count): int => (int) $count)->all(),
        ];
    }
}
