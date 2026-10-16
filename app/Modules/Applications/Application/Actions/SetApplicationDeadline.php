<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Infrastructure\Models\Application;
use Carbon\CarbonImmutable;

/** The date is the person's own; changing it earns a fresh warning. */
final readonly class SetApplicationDeadline
{
    public function handle(Application $application, ?CarbonImmutable $deadline): Application
    {
        $application->forceFill(['deadline_on' => $deadline, 'deadline_reminded_at' => null])->save();

        return $application;
    }
}
