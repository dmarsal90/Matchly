<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Actions\BuildSearchProfile;
use App\Modules\Profiles\Application\Actions\UpdateSearchProfile;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use App\Modules\Profiles\Http\Requests\UpdateSearchProfileRequest;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class SearchProfileController
{
    public function update(UpdateSearchProfileRequest $request, #[CurrentUser] User $user, UpdateSearchProfile $update, SkillCatalog $catalog): RedirectResponse
    {
        $update->handle($user, $request->toSearchProfile($catalog));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('skills.search_profile.saved')]);

        return back();
    }

    public function regenerate(#[CurrentUser] User $user, BuildSearchProfile $build): RedirectResponse
    {
        $build->handle($user, force: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('skills.search_profile.regenerated')]);

        return back();
    }
}
