<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Domain\Data\SearchProfile;
use Illuminate\Contracts\Events\Dispatcher;

/** Saves the user's own version of the search profile; it is no longer regenerated automatically. */
final readonly class UpdateSearchProfile
{
    public function __construct(private Dispatcher $events) {}

    public function handle(User $user, SearchProfile $searchProfile): void
    {
        $profile = $user->profile()->firstOrNew();
        $profile->search_profile = [...$searchProfile->toArray(), 'edited' => true];
        $profile->save();

        $this->events->dispatch(new ProfileChanged($user->id));
    }
}
