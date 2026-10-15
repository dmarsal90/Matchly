<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Application\Actions;

use App\Modules\Alerts\Domain\Enums\AlertFrequency;
use App\Modules\Alerts\Infrastructure\Models\SavedSearch;
use App\Modules\Notifications\Application\Notifications\JobAlertNotification;
use Carbon\CarbonImmutable;

/** Sends the daily or weekly digest of every alert with jobs waiting. */
final readonly class SendAlertDigests
{
    /**
     * @return int digests sent
     */
    public function handle(AlertFrequency $frequency): int
    {
        $sent = 0;

        SavedSearch::query()
            ->where('active', true)
            ->where('frequency', $frequency)
            ->whereHas('deliveries', fn ($deliveries) => $deliveries->whereNull('notified_at'))
            ->with('user')
            ->each(function (SavedSearch $search) use ($frequency, &$sent): void {
                $pending = $search->deliveries()->whereNull('notified_at');
                // Jobs closed meanwhile are left out of the digest.
                $jobIds = (clone $pending)->whereHas('job', fn ($job) => $job->visible())
                    ->orderByDesc('matched_at')->orderByDesc('job_listing_id')
                    ->pluck('job_listing_id')->map(intval(...))->values()->all();

                $now = CarbonImmutable::now();
                $pending->update(['notified_at' => $now]);

                if ($jobIds === [] || $search->user === null) {
                    return;
                }

                $search->user->notify(new JobAlertNotification($search->id, array_values($jobIds), $frequency));
                $search->forceFill(['last_notified_at' => $now])->save();
                $sent++;
            });

        return $sent;
    }
}
