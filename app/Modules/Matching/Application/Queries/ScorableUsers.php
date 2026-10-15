<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Queries;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Users with enough of a profile to be scored: a finished questionnaire or any skill. */
final class ScorableUsers
{
    /**
     * @return Builder<User>
     */
    public static function query(): Builder
    {
        return User::query()
            ->whereNotNull('email_verified_at')
            ->where(fn (Builder $query) => $query
                ->whereHas('profile', fn (Builder $profile) => $profile->whereNotNull('onboarding_completed_at'))
                ->orWhereHas('skills'));
    }
}
