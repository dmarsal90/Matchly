<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Actions\ToggleSaved;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class SavedJobController
{
    public function toggle(JobListing $job, #[CurrentUser] User $user, ToggleSaved $toggle): RedirectResponse
    {
        // Same visibility rule as the detail page: jobs held for moderation do not exist for users.
        abort_if($job->status === JobStatus::Flagged && ! $user->is_admin, 404);

        $toggle->handle($user, $job);

        return back();
    }
}
