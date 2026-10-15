<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Listeners;

use App\Modules\Matching\Infrastructure\Queue\RecalculateMatchesJob;
use App\Modules\Profiles\Application\Events\ProfileChanged;

final class RecalculateMatchesOnProfileChange
{
    public function handle(ProfileChanged $event): void
    {
        RecalculateMatchesJob::dispatch($event->userId)->delay((int) config('matching.recalculate_delay', 15));
    }
}
