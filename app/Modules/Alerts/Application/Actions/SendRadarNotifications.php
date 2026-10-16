<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Application\Actions;

use App\Modules\Alerts\Infrastructure\Models\FollowedCompany;
use App\Modules\Applications\Domain\Services\CompanyKey;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Notifications\Application\Notifications\RadarNotification;
use Carbon\CarbonImmutable;

/**
 * Tells people when a company they follow has a new open job. It looks only at
 * listings Matchly itself has ingested since the last check: it does not visit
 * any company site. One notification per company and run, at most MAX_JOBS jobs.
 */
final readonly class SendRadarNotifications
{
    public const int MAX_JOBS = 5;

    /** Never look further back than this, even for a follow nobody checked for a long time. */
    private const int MAX_DAYS = 14;

    /**
     * @return int notifications sent
     */
    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $follows = FollowedCompany::query()->with('user')->get();
        if ($follows->isEmpty()) {
            return 0;
        }

        $since = max($follows->min('last_checked_at'), $now->subDays(self::MAX_DAYS));
        $keys = array_flip($follows->pluck('company_key')->unique()->all());

        /** @var array<string, list<array{id: int, title: string, first_seen_at: CarbonImmutable}>> $news */
        $news = [];
        JobListing::query()->visible()->where('first_seen_at', '>', $since)->select(['id', 'title', 'company', 'first_seen_at'])->orderBy('first_seen_at')
            ->lazyById(500)
            ->each(function (JobListing $job) use ($keys, &$news): void {
                $key = CompanyKey::from($job->company);
                if (isset($keys[$key])) {
                    $news[$key][] = ['id' => $job->id, 'title' => $job->title, 'first_seen_at' => CarbonImmutable::parse($job->first_seen_at)];
                }
            });

        $sent = 0;
        foreach ($follows as $follow) {
            $fresh = array_values(array_filter($news[$follow->company_key] ?? [], fn (array $job): bool => $job['first_seen_at']->greaterThan($follow->last_checked_at)));

            if ($fresh !== [] && $follow->user !== null) {
                $follow->user->notify(new RadarNotification($follow->company, count($fresh), array_map(fn (array $job): array => ['id' => $job['id'], 'title' => $job['title']], array_slice($fresh, 0, self::MAX_JOBS))));
                $follow->last_notified_at = $now;
                $sent++;
            }

            $follow->last_checked_at = $now;
            $follow->save();
        }

        return $sent;
    }
}
