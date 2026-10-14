<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Policies;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;

final class CvDocumentPolicy
{
    public function view(User $user, CvDocument $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function update(User $user, CvDocument $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function delete(User $user, CvDocument $document): bool
    {
        return $document->user_id === $user->id;
    }
}
