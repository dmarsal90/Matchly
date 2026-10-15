<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\JobListings;

use App\Filament\Admin\Resources\JobListings\Pages\ManageJobListings;
use App\Modules\Jobs\Application\Actions\ModerateJob;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class JobListingResource extends Resource
{
    protected static ?string $model = JobListing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'job';
    }

    /** Jobs waiting for moderation. */
    public static function getNavigationBadge(): ?string
    {
        $flagged = JobListing::query()->where('status', JobStatus::Flagged)->count();

        return $flagged > 0 ? (string) $flagged : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('title')->columnSpan(2),
                TextEntry::make('company'),
                TextEntry::make('source.name')->label('Source'),
                TextEntry::make('status')->badge(),
                TextEntry::make('flag_reasons')->badge()->color('danger')->placeholder('—'),
                TextEntry::make('work_mode')->badge(),
                TextEntry::make('remote_scope')->placeholder('unknown'),
                TextEntry::make('allowed_regions')->badge()->placeholder('—'),
                TextEntry::make('location')->placeholder('—'),
                TextEntry::make('posted_at')->dateTime()->placeholder('—'),
                TextEntry::make('apply_url')->url(fn (JobListing $record): string => $record->apply_url, shouldOpenInNewTab: true)->limit(60),
            ]),
            Section::make('Description')->collapsed()->schema([
                // Stored HTML is sanitized with HTMLPurifier at ingestion.
                TextEntry::make('description')->html()->hiddenLabel(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('source'))
            ->defaultSort('sort_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->limit(60)->description(fn (JobListing $record): string => $record->company),
                TextColumn::make('source.name')->label('Source')->sortable(),
                TextColumn::make('status')->badge()->color(fn (JobStatus $state): string => match ($state) {
                    JobStatus::Open => 'success',
                    JobStatus::Flagged => 'danger',
                    JobStatus::Closed => 'gray',
                }),
                TextColumn::make('work_mode')->badge(),
                TextColumn::make('remote_scope')->placeholder('unknown')->toggleable(),
                TextColumn::make('sort_at')->label('Posted')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(JobStatus::class),
                SelectFilter::make('source')->relationship('source', 'name'),
                SelectFilter::make('work_mode')->options(WorkMode::class),
                TernaryFilter::make('duplicate')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('duplicate_of_id'),
                        false: fn (Builder $query) => $query->whereNull('duplicate_of_id'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (JobListing $record): bool => $record->status === JobStatus::Flagged)
                    ->action(function (JobListing $record, ModerateJob $moderate): void {
                        $moderate->approve($record);
                        Notification::make()->title('Job published')->success()->send();
                    }),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (JobListing $record): bool => $record->status !== JobStatus::Closed)
                    ->action(function (JobListing $record, ModerateJob $moderate): void {
                        $moderate->reject($record);
                        Notification::make()->title('Job closed')->success()->send();
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageJobListings::route('/')];
    }
}
