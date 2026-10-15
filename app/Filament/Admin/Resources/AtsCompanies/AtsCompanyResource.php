<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AtsCompanies;

use App\Filament\Admin\Resources\AtsCompanies\Pages\ManageAtsCompanies;
use App\Modules\Providers\Infrastructure\Models\AtsCompany;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Companies whose public ATS boards are polled by the Greenhouse/Lever/Ashby/Workable/SmartRecruiters sources. */
final class AtsCompanyResource extends Resource
{
    protected static ?string $model = AtsCompany::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return 'ATS company';
    }

    public static function form(Schema $schema): Schema
    {
        $platforms = array_combine(AtsCompany::PLATFORMS, AtsCompany::PLATFORMS);

        return $schema->components([
            Select::make('platform')->options($platforms)->required(),
            TextInput::make('identifier')
                ->helperText('Board token / account slug as it appears in the public board URL (case-sensitive on SmartRecruiters).')
                ->required()
                ->maxLength(255)
                ->regex('/^[A-Za-z0-9._-]+$/')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, callable $get): Unique => $rule->where('platform', $get('platform'))),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('website_url')->url()->maxLength(255),
            Toggle::make('enabled')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('platform')->badge()->sortable(),
                TextColumn::make('identifier')->searchable()->copyable(),
                ToggleColumn::make('enabled'),
                TextColumn::make('last_job_count')->label('Jobs (last run)')->numeric()->sortable(),
                TextColumn::make('last_fetched_at')->since()->sortable()->placeholder('never'),
                TextColumn::make('last_error')->limit(50)->color('danger')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('platform')->options(array_combine(AtsCompany::PLATFORMS, AtsCompany::PLATFORMS)),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAtsCompanies::route('/')];
    }
}
