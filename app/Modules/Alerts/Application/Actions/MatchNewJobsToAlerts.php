<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Application\Actions;

use App\Modules\Alerts\Domain\Enums\AlertFrequency;
use App\Modules\Alerts\Infrastructure\Models\AlertDelivery;
use App\Modules\Alerts\Infrastructure\Models\SavedSearch;
use App\Modules\Jobs\Application\Queries\JobSearchQuery;
use App\Modules\Matching\Application\Actions\ScoreJobsForUser;
use App\Modules\Notifications\Application\Notifications\JobAlertNotification;
use Carbon\CarbonImmutable;

/**
 * Compares newly ingested jobs with every active alert. Each (alert, job)
 * pair is recorded once, so a job is never notified twice for the same
 * alert. Immediate alerts are notified now; daily and weekly ones wait for
 * their digest.
 */
final readonly class MatchNewJobsToAlerts
{
    private const int CHUNK = 200;

    public function __construct(
        private JobSearchQuery $search,
        private ScoreJobsForUser $score,
    ) {}

    /**
     * @param  list<int>  $jobIds
     * @return int alerts that matched something
     */
    public function handle(array $jobIds): int
    {
        if ($jobIds === []) {
            return 0;
        }

        $matched = 0;
        $scored = [];

        SavedSearch::query()->where('active', true)->with('user')->chunkById(self::CHUNK, function ($searches) use ($jobIds, &$matched, &$scored): void {
            foreach ($searches as $search) {
                $user = $search->user;
                if ($user === null || $user->email_verified_at === null) {
                    continue;
                }

                $filters = $search->jobFilters();
                // A score filter needs the new jobs scored for this user first (once per run).
                if ($filters->minScore !== null && ! isset($scored[$user->id])) {
                    $this->score->handle($user, $jobIds);
                    $scored[$user->id] = true;
                }

                $ids = $this->search->matchingIds($filters, $jobIds);
                $new = array_values(array_diff($ids, $search->deliveries()->whereIn('job_listing_id', $ids)->pluck('job_listing_id')->map(intval(...))->all()));
                if ($new === []) {
                    continue;
                }

                $now = CarbonImmutable::now();
                $immediate = $search->frequency === AlertFrequency::Immediate;
                AlertDelivery::query()->insertOrIgnore(array_map(fn (int $jobId): array => [
                    'saved_search_id' => $search->id,
                    'job_listing_id' => $jobId,
                    'matched_at' => $now,
                    'notified_at' => $immediate ? $now : null,
                ], $new));

                if ($immediate) {
                    $user->notify(new JobAlertNotification($search->id, $new, AlertFrequency::Immediate));
                    $search->forceFill(['last_notified_at' => $now])->save();
                }

                $matched++;
            }
        });

        return $matched;
    }
}
