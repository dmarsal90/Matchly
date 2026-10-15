<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Policies;

use App\Modules\Alerts\Infrastructure\Models\SavedSearch;
use App\Modules\Identity\Infrastructure\Models\User;

final class SavedSearchPolicy
{
    public function update(User $user, SavedSearch $search): bool
    {
        return $search->user_id === $user->id;
    }

    public function delete(User $user, SavedSearch $search): bool
    {
        return $search->user_id === $user->id;
    }
}
