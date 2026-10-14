<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Console;

use App\Modules\Providers\Application\ProviderRegistry;
use App\Modules\Providers\Infrastructure\Models\Source;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jobs:sources')]
#[Description('Show every job source with its status and last run')]
final class ListSourcesCommand extends Command
{
    public function handle(ProviderRegistry $registry): int
    {
        $records = Source::query()->withCount(['jobs as open_jobs_count' => fn ($query) => $query->where('status', 'open')])->get()->keyBy('key');

        $this->table(
            ['Source', 'Config', 'Admin', 'Open jobs', 'Last fetch', 'Failures', 'Last error'],
            array_map(function (string $key) use ($registry, $records): array {
                /** @var Source|null $record */
                $record = $records->get($key);

                return [
                    $key,
                    in_array($key, $registry->enabled(), true) ? 'on' : 'off',
                    $record === null ? '-' : ($record->enabled ? 'on' : 'off'),
                    $record?->getAttribute('open_jobs_count') ?? 0,
                    $record?->last_fetched_at?->diffForHumans() ?? 'never',
                    $record->consecutive_failures ?? 0,
                    str($record->last_error ?? '')->limit(60)->toString(),
                ];
            }, $registry->keys()),
        );

        return self::SUCCESS;
    }
}
