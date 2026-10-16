<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Notifications\Application\Notifications\DeadlineReminderNotification;
use Carbon\CarbonImmutable;

/**
 * Warns people a couple of days before a deadline they set themselves, once
 * per date. Applications that are already closed are left alone.
 */
final readonly class SendDeadlineReminders
{
    /**
     * @return int reminders sent
     */
    public function handle(?int $days = null): int
    {
        $days ??= (int) config('applications.deadline_warning_days');
        $today = CarbonImmutable::today();
        $sent = 0;

        Application::query()
            ->whereNotNull('deadline_on')
            ->whereNull('deadline_reminded_at')
            ->whereBetween('deadline_on', [$today->toDateString(), $today->addDays($days)->toDateString()])
            ->whereNotIn('status', array_map(fn (ApplicationStatus $status): string => $status->value, array_filter(ApplicationStatus::cases(), fn (ApplicationStatus $status): bool => $status->isClosed())))
            ->with('user')
            ->each(function (Application $application) use ($today, &$sent): void {
                $user = $application->user;
                $deadline = $application->deadline_on;
                if ($user === null || $deadline === null) {
                    return;
                }

                $application->forceFill(['deadline_reminded_at' => CarbonImmutable::now()])->save();
                $user->notify(new DeadlineReminderNotification($application->id, $deadline->toDateString(), (int) $today->diffInDays($deadline)));
                $sent++;
            });

        return $sent;
    }
}
