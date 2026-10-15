<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sources;

use App\Filament\Admin\Resources\Sources\Pages\ManageSources;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Providers\Infrastructure\Models\Source;
use App\Modules\Providers\Infrastructure\Queue\IngestProviderJob;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Job sources: the rows exist per provider in config/jobs.php; admins only switch them and trigger runs. */
final class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return 'job source';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'jobs as open_jobs_count' => fn (Builder $jobs) => $jobs->getQuery()->where('status', JobStatus::Open->value)->whereNull('duplicate_of_id'),
            ]))
            ->defaultSort('key')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Source $record): string => $record->key),
                ToggleColumn::make('enabled')->label('Enabled'),
                TextColumn::make('open_jobs_count')->label('Open jobs')->numeric()->sortable(),
                TextColumn::make('last_fetched_at')->since()->sortable()->placeholder('never'),
                TextColumn::make('last_succeeded_at')->since()->placeholder('never'),
                TextColumn::make('consecutive_failures')
                    ->label('Failures')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'success',
                        $state < 3 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('last_error')->limit(60)->tooltip(fn (Source $record): ?string => $record->last_error)->placeholder('—'),
            ])
            ->recordActions([
                Action::make('ingest')
                    ->label('Ingest now')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->action(function (Source $record): void {
                        IngestProviderJob::dispatch($record->key);
                        Notification::make()->title("Ingestion of {$record->name} queued")->success()->send();
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageSources::route('/')];
    }
}
