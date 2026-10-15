<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Modules\AI\Infrastructure\Models\AiUsage;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** AI spending this month on the server's keys, and how reliable the providers were. */
final class AiUsageOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        $month = AiUsage::query()->where('created_at', '>=', CarbonImmutable::now()->startOfMonth());

        $serverCost = (float) (clone $month)->where('key_owner', AiUsage::SERVER_KEY)->sum('cost_usd');
        $systemCost = (float) (clone $month)->where('key_owner', AiUsage::SERVER_KEY)->whereNull('user_id')->sum('cost_usd');
        $calls = (clone $month)->count();
        $failed = (clone $month)->where('success', false)->count();
        $byProvider = (clone $month)->where('success', true)->toBase()->selectRaw('provider, COUNT(*) AS total')->groupBy('provider')->pluck('total', 'provider')
            ->map(fn (mixed $total, string $provider): string => "{$provider}: {$total}")->implode(' · ');

        return [
            Stat::make('AI cost this month (server keys)', '$'.number_format($serverCost, 2))
                ->description('System tasks: $'.number_format($systemCost, 2).' of $'.number_format((float) config('ai.budget.system_monthly_usd'), 2)),
            Stat::make('AI calls this month', number_format($calls))->description($byProvider !== '' ? $byProvider : 'No calls yet'),
            Stat::make('Failed AI calls', number_format($failed))->color($calls > 0 && $failed / $calls > 0.2 ? 'danger' : 'success'),
        ];
    }
}
