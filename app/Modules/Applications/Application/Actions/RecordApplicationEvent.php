<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationEvent;
use Carbon\CarbonImmutable;

/** Adds one entry to an application's history. */
final class RecordApplicationEvent
{
    public function handle(
        Application $application,
        ApplicationEventType $type,
        ?ApplicationStatus $status = null,
        ?int $round = null,
        ?string $note = null,
        ?CarbonImmutable $occurredAt = null,
    ): ApplicationEvent {
        $event = new ApplicationEvent([
            'type' => $type,
            'status' => $status,
            'round' => $round,
            'note' => $note,
            'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
        ]);
        $event->application()->associate($application);
        $event->save();

        return $event;
    }

    public function statusChanged(Application $application, ApplicationStatus $status, ?CarbonImmutable $occurredAt = null): ApplicationEvent
    {
        return $this->handle($application, ApplicationEventType::StatusChanged, status: $status, occurredAt: $occurredAt);
    }
}
