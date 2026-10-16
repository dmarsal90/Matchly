<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Application\Privacy;

use App\Modules\Alerts\Infrastructure\Models\FollowedCompany;
use App\Modules\Alerts\Infrastructure\Models\SavedSearch;
use App\Modules\Identity\Domain\Contracts\PersonalDataContributor;
use App\Modules\Identity\Domain\Data\PersonalData;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Notifications\DatabaseNotification;

final readonly class AlertsDataContributor implements PersonalDataContributor
{
    public function export(int $userId): PersonalData
    {
        return new PersonalData('alerts', [
            'saved_searches' => SavedSearch::query()->where('user_id', $userId)->withCount('deliveries')->orderBy('id')->get()
                ->map(fn (SavedSearch $search): array => [
                    'name' => $search->name,
                    'filters' => $search->filters,
                    'frequency' => $search->frequency->value,
                    'email' => $search->email,
                    'active' => $search->active,
                    'jobs_matched' => (int) $search->getAttribute('deliveries_count'),
                    'created_at' => $search->created_at->toIso8601String(),
                ])->values()->all(),
            'followed_companies' => FollowedCompany::query()->where('user_id', $userId)->orderBy('id')->get()
                ->map(fn (FollowedCompany $follow): array => ['company' => $follow->company, 'followed_at' => $follow->created_at->toIso8601String(), 'last_notified_at' => $follow->last_notified_at?->toIso8601String()])->values()->all(),
            'notifications' => DatabaseNotification::query()
                ->where('notifiable_type', (new User)->getMorphClass())
                ->where('notifiable_id', $userId)
                ->orderBy('created_at')
                ->get()
                ->map(fn (DatabaseNotification $notification): array => [
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ])->values()->all(),
            'notification_preferences' => User::query()->whereKey($userId)->value('notification_preferences'),
        ]);
    }

    /** Notifications are polymorphic (no foreign key to cascade): delete them explicitly. */
    public function erase(int $userId): void
    {
        DatabaseNotification::query()
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('notifiable_id', $userId)
            ->delete();
    }
}
