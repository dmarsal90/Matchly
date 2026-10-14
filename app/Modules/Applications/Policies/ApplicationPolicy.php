<?php

declare(strict_types=1);

namespace App\Modules\Applications\Policies;

use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Identity\Infrastructure\Models\User;

final class ApplicationPolicy
{
    public function update(User $user, Application $application): bool
    {
        return $application->user_id === $user->id;
    }

    public function delete(User $user, Application $application): bool
    {
        return $application->user_id === $user->id;
    }
}
