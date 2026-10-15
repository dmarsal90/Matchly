<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Providers\Infrastructure\Models\Source;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class IngestionOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $visible = JobListing::query()->visible()->count();
        $newToday = JobListing::query()->whereNull('duplicate_of_id')->where('first_seen_at', '>=', CarbonImmutable::now()->subDay())->count();
        $flagged = JobListing::query()->where('status', JobStatus::Flagged)->count();
        $failing = Source::query()->where('consecutive_failures', '>', 0)->count();

        return [
            Stat::make('Visible jobs', number_format($visible)),
            Stat::make('New in the last 24 h', number_format($newToday)),
            Stat::make('Waiting for moderation', (string) $flagged)->color($flagged > 0 ? 'danger' : 'success'),
            Stat::make('Failing sources', (string) $failing)->color($failing > 0 ? 'warning' : 'success'),
        ];
    }
}
