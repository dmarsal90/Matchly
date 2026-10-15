<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IngestionRuns;

use App\Filament\Admin\Resources\IngestionRuns\Pages\ManageIngestionRuns;
use App\Modules\Providers\Infrastructure\Models\IngestionRun;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class IngestionRunResource extends Resource
{
    protected static ?string $model = IngestionRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 40;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('source.name')->label('Source'),
            TextEntry::make('status')->badge(),
            TextEntry::make('started_at')->dateTime(),
            TextEntry::make('fetched')->numeric(),
            TextEntry::make('created')->numeric(),
            TextEntry::make('updated')->numeric(),
            TextEntry::make('skipped')->numeric(),
            TextEntry::make('duplicates')->numeric(),
            TextEntry::make('closed')->numeric(),
            TextEntry::make('skip_reasons')
                ->formatStateUsing(fn (string $state, IngestionRun $record): string => collect($record->skip_reasons ?? [])->map(fn (int $count, string $reason): string => "{$reason}: {$count}")->implode(' · '))
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('failures')->listWithLineBreaks()->placeholder('—')->columnSpanFull(),
            TextEntry::make('error')->placeholder('—')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('source'))
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('source.name')->label('Source'),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    IngestionRun::SUCCEEDED => 'success',
                    IngestionRun::PARTIAL => 'warning',
                    IngestionRun::FAILED => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('fetched')->numeric(),
                TextColumn::make('created')->numeric(),
                TextColumn::make('skipped')->numeric(),
                TextColumn::make('duplicates')->numeric(),
                TextColumn::make('closed')->numeric(),
                TextColumn::make('duration')
                    ->state(fn (IngestionRun $record): ?string => $record->finished_at !== null
                        ? $record->started_at->diffInSeconds($record->finished_at, true).' s'
                        : null)
                    ->placeholder('running'),
                TextColumn::make('started_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')->relationship('source', 'name'),
                SelectFilter::make('status')->options(array_combine(
                    [IngestionRun::RUNNING, IngestionRun::SUCCEEDED, IngestionRun::PARTIAL, IngestionRun::FAILED],
                    [IngestionRun::RUNNING, IngestionRun::SUCCEEDED, IngestionRun::PARTIAL, IngestionRun::FAILED],
                )),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageIngestionRuns::route('/')];
    }
}
