<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Modules\Identity\Application\Actions\DeleteAccount;
use App\Modules\Identity\Infrastructure\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Account administration. Profiles, CVs and imports are personal data and are
 * deliberately not browsable here; deleting goes through DeleteAccount.
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (User $record): string => $record->email),
                TextColumn::make('email')->searchable()->hidden(),
                IconColumn::make('email_verified_at')->label('Verified')->boolean(),
                ToggleColumn::make('is_admin')
                    ->label('Admin')
                    // Admins cannot demote themselves and lock everyone out.
                    ->disabled(fn (User $record): bool => $record->id === Auth::id()),
                TextColumn::make('last_active_at')->since()->sortable()->placeholder('never'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')->label('Admins'),
                TernaryFilter::make('email_verified_at')->label('Verified')->nullable(),
            ])
            ->recordActions([
                Action::make('delete')
                    ->label('Delete account')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Deletes the account and all personal data (profile, CVs, imports). This cannot be undone.')
                    ->hidden(fn (User $record): bool => $record->id === Auth::id())
                    ->action(function (User $record, DeleteAccount $deleteAccount): void {
                        $deleteAccount->handle($record);
                        Notification::make()->title('Account deleted')->success()->send();
                    }),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->select(['id', 'name', 'email', 'email_verified_at', 'is_admin', 'last_active_at', 'created_at']));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
