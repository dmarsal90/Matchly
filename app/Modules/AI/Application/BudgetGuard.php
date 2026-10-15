<?php

declare(strict_types=1);

namespace App\Modules\AI\Application;

use App\Modules\AI\Infrastructure\Models\AiUsage;
use Carbon\CarbonImmutable;

/**
 * Monthly spending caps on the server's keys: one per user, and one shared
 * by system tasks (user_id null). Calls made with a user's own key never
 * count.
 */
final readonly class BudgetGuard
{
    public function __construct(
        private float $userMonthlyUsd,
        private float $systemMonthlyUsd,
    ) {}

    public function spent(?int $userId): float
    {
        return (float) AiUsage::query()
            ->where('key_owner', AiUsage::SERVER_KEY)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->when($userId === null, fn ($query) => $query->whereNull('user_id'), fn ($query) => $query->where('user_id', $userId))
            ->sum('cost_usd');
    }

    public function limit(?int $userId): float
    {
        return $userId === null ? $this->systemMonthlyUsd : $this->userMonthlyUsd;
    }

    public function exceeded(?int $userId): bool
    {
        return $this->spent($userId) >= $this->limit($userId);
    }
}
