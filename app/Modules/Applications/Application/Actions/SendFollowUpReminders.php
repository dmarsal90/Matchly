<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Notifications\Application\Notifications\FollowUpReminderNotification;
use Carbon\CarbonImmutable;

/**
 * Reminds people about applications still waiting for an answer after N days.
 * Each application is reminded once, and not at all when the person has
 * already followed up or the job was closed.
 */
final readonly class SendFollowUpReminders
{
    /**
     * @return int reminders sent
     */
    public function handle(?int $days = null): int
    {
        $days ??= (int) config('applications.follow_up_days');
        $now = CarbonImmutable::now();
        $sent = 0;

        Application::query()
            ->where('status', ApplicationStatus::Applied)
            ->whereNull('follow_up_reminded_at')
            ->where('applied_at', '<=', $now->subDays($days))
            ->whereDoesntHave('events', fn ($events) => $events->where('type', ApplicationEventType::FollowUp))
            ->whereHas('job', fn ($job) => $job->visible())
            ->with('user')
            ->each(function (Application $application) use ($days, $now, &$sent): void {
                $application->forceFill(['follow_up_reminded_at' => $now])->save();

                if ($application->user === null) {
                    return;
                }

                $application->user->notify(new FollowUpReminderNotification($application->id, $days));
                $sent++;
            });

        return $sent;
    }
}
