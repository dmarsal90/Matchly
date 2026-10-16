<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationEvent;
use Carbon\CarbonImmutable;

/**
 * The person records something that happened elsewhere: an interview
 * round, a follow-up they sent, or a free note. An interview moves the
 * application to "interview" if it was still earlier in the pipeline.
 */
final readonly class LogApplicationEvent
{
    public function __construct(private RecordApplicationEvent $record, private UpdateApplication $update) {}

    public function handle(Application $application, ApplicationEventType $type, ?string $note, CarbonImmutable $occurredAt): ApplicationEvent
    {
        $round = null;
        if ($type === ApplicationEventType::Interview) {
            $round = (int) $application->events()->where('type', ApplicationEventType::Interview->value)->max('round') + 1;
        }

        $event = $this->record->handle($application, $type, round: $round, note: $note, occurredAt: $occurredAt);

        if ($type === ApplicationEventType::Interview && ! $application->status->isPastApplying()) {
            $this->update->handle($application, ApplicationStatus::Interview, $application->notes);
        }

        return $event;
    }
}
