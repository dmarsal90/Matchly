<?php

declare(strict_types=1);

namespace App\Modules\Applications\Policies;

use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Identity\Infrastructure\Models\User;

final class ApplicationPackagePolicy
{
    public function view(User $user, ApplicationPackage $package): bool
    {
        return $package->user_id === $user->id;
    }

    public function update(User $user, ApplicationPackage $package): bool
    {
        return $package->user_id === $user->id;
    }

    public function delete(User $user, ApplicationPackage $package): bool
    {
        return $package->user_id === $user->id;
    }
}
