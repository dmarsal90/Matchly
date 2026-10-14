<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Who can open /horizon outside the local environment.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            /** @var list<string> $admins */
            $admins = config('horizon.admin_emails', []);

            return $user !== null && in_array(strtolower($user->email), $admins, true);
        });
    }
}
