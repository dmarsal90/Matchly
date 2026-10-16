<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Notifications\Application\Notifications\FollowUpReminderNotification;
use Carbon\CarbonImmutable;

/**
 * Reminds people about applications still waiting for an answer after N days:
 * once for the first follow-up and, if they followed up and still heard
 * nothing, once more N days after that. Never more than two follow-ups per
 * application, and nothing for applications that moved on or whose job closed.
 */
final readonly class SendFollowUpReminders
{
    private const int MAX_FOLLOW_UPS = 2;

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
            ->where('applied_at', '<=', $now->subDays($days))
            ->whereHas('job', fn ($job) => $job->visible())
            ->with(['user', 'events' => fn ($events) => $events->where('type', ApplicationEventType::FollowUp)])
            ->each(function (Application $application) use ($days, $now, &$sent): void {
                $user = $application->user;
                if ($user === null || ! $this->isDue($application, $days, $now)) {
                    return;
                }

                $application->forceFill(['follow_up_reminded_at' => $now])->save();
                $user->notify(new FollowUpReminderNotification($application->id, $days));
                $sent++;
            });

        return $sent;
    }

    private function isDue(Application $application, int $days, CarbonImmutable $now): bool
    {
        $followUps = $application->events;
        if ($followUps->count() >= self::MAX_FOLLOW_UPS) {
            return false;
        }

        // Waiting since the last thing the person did: applying, or following up.
        $last = $followUps->max('occurred_at');
        $since = $last ?? $application->applied_at;
        if ($since === null || $since->greaterThan($now->subDays($days))) {
            return false;
        }

        // One reminder per wait: not again until they have followed up.
        return $application->follow_up_reminded_at === null || $application->follow_up_reminded_at->lessThan($since);
    }
}
