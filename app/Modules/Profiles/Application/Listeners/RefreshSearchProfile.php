<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Listeners;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Actions\BuildSearchProfile;
use App\Modules\Profiles\Application\Events\ProfileChanged;

/** Keeps the suggested search profile in step with the profile it comes from. */
final readonly class RefreshSearchProfile
{
    public function __construct(private BuildSearchProfile $build) {}

    public function handle(ProfileChanged $event): void
    {
        $user = User::query()->find($event->userId);

        if ($user !== null) {
            $this->build->handle($user);
        }
    }
}
