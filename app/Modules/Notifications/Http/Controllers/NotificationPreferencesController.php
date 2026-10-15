<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Application\Actions\RevokeConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** How the user wants to hear about new jobs: e-mail (a consent) and live in-app pushes. */
final class NotificationPreferencesController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/Notifications', [
            'email' => $user->acceptsEmailAlerts(),
            'realtime' => $user->wantsRealtimeNotifications(),
            'alerts' => $user->savedSearches()->count(),
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user, GrantConsent $grant, RevokeConsent $revoke): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'boolean'],
            'realtime' => ['required', 'boolean'],
        ]);

        $data['email'] ? $grant->handle($user, ConsentPurpose::EmailAlerts) : $revoke->handle($user, ConsentPurpose::EmailAlerts);
        $user->forceFill(['notification_preferences' => [...($user->notification_preferences ?? []), 'realtime' => (bool) $data['realtime']]])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('alerts.preferences.saved')]);

        return back();
    }
}
