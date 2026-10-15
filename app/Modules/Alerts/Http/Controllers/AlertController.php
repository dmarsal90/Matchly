<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Http\Controllers;

use App\Modules\Alerts\Domain\Enums\AlertFrequency;
use App\Modules\Alerts\Http\Requests\StoreAlertRequest;
use App\Modules\Alerts\Http\Requests\UpdateAlertRequest;
use App\Modules\Alerts\Infrastructure\Models\SavedSearch;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Saved searches as alerts: create, edit, pause and delete. */
final class AlertController
{
    public function index(#[CurrentUser] User $user): Response
    {
        $alerts = $user->savedSearches()
            ->withCount(['deliveries', 'deliveries as pending_count' => fn ($query) => $query->whereNull('notified_at')])
            ->latest('id')
            ->get();

        return Inertia::render('alerts/Index', [
            'alerts' => $alerts->map(fn (SavedSearch $search): array => [
                'id' => $search->id,
                'name' => $search->name,
                'filters' => $search->filters,
                'frequency' => $search->frequency->value,
                'email' => $search->email,
                'active' => $search->active,
                'matches' => (int) $search->getAttribute('deliveries_count'),
                'pending' => (int) $search->getAttribute('pending_count'),
                'last_notified_at' => $search->last_notified_at?->toIso8601String(),
                'created_at' => $search->created_at->toIso8601String(),
            ])->values()->all(),
            'emailConsented' => $user->acceptsEmailAlerts(),
            'frequencies' => array_column(AlertFrequency::cases(), 'value'),
            'maxAlerts' => StoreAlertRequest::MAX_ALERTS,
        ]);
    }

    public function store(StoreAlertRequest $request, #[CurrentUser] User $user, GrantConsent $grantConsent): RedirectResponse
    {
        if ($user->savedSearches()->count() >= StoreAlertRequest::MAX_ALERTS) {
            throw ValidationException::withMessages(['name' => __('alerts.too_many', ['max' => StoreAlertRequest::MAX_ALERTS])]);
        }

        if ($request->boolean('email')) {
            $grantConsent->handle($user, ConsentPurpose::EmailAlerts);
        }

        $user->savedSearches()->create([
            'name' => trim((string) $request->validated('name')),
            'filters' => $request->filters(),
            'frequency' => AlertFrequency::from((string) $request->validated('frequency')),
            'email' => $request->boolean('email'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('alerts.created')]);

        return back();
    }

    public function update(UpdateAlertRequest $request, SavedSearch $alert, #[CurrentUser] User $user, GrantConsent $grantConsent): RedirectResponse
    {
        Gate::authorize('update', $alert);

        if ($request->boolean('email')) {
            $grantConsent->handle($user, ConsentPurpose::EmailAlerts);
        }

        $alert->fill($request->changes())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('alerts.updated')]);

        return back();
    }

    public function destroy(SavedSearch $alert): RedirectResponse
    {
        Gate::authorize('delete', $alert);

        $alert->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('alerts.deleted')]);

        return back();
    }
}
