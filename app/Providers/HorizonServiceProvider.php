<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Who can open /horizon outside the local environment: admins, plus the
     * e-mails in HORIZON_ADMIN_EMAILS (handy before the first admin exists).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            /** @var list<string> $admins */
            $admins = config('horizon.admin_emails', []);

            return $user !== null && ($user->is_admin || in_array(strtolower($user->email), $admins, true));
        });
    }
}
