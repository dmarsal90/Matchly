<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use Carbon\CarbonImmutable;

final readonly class UpdateApplication
{
    public function __construct(private RecordApplicationEvent $record) {}

    public function handle(Application $application, ApplicationStatus $status, ?string $notes): Application
    {
        $changed = $application->status !== $status;

        if ($changed) {
            $application->status = $status;
            $application->status_changed_at = CarbonImmutable::now();

            if ($status === ApplicationStatus::Applied && $application->applied_at === null) {
                $application->applied_at = CarbonImmutable::now();
            }
        }

        $application->notes = $notes;
        $application->save();

        if ($changed) {
            $this->record->statusChanged($application, $status, $application->status_changed_at);
        }

        return $application;
    }
}
