<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Actions\DeleteSourceData;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class SourceDataController
{
    public function destroy(SkillSource $source, #[CurrentUser] User $user, DeleteSourceData $delete): RedirectResponse
    {
        $delete->handle($user->id, $source);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('skills.sources.deleted', ['source' => __('skills.sources.'.$source->value.'.title')])]);

        return to_route('skills.index');
    }
}
