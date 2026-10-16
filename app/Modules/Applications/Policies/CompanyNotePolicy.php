<?php

declare(strict_types=1);

namespace App\Modules\Applications\Policies;

use App\Modules\Applications\Infrastructure\Models\CompanyNote;
use App\Modules\Identity\Infrastructure\Models\User;

final class CompanyNotePolicy
{
    public function update(User $user, CompanyNote $note): bool
    {
        return $note->user_id === $user->id;
    }

    public function delete(User $user, CompanyNote $note): bool
    {
        return $note->user_id === $user->id;
    }
}
