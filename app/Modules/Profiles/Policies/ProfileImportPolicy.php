<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Policies;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;

final class ProfileImportPolicy
{
    public function view(User $user, ProfileImport $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function update(User $user, ProfileImport $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function delete(User $user, ProfileImport $import): bool
    {
        return $import->user_id === $user->id;
    }
}
