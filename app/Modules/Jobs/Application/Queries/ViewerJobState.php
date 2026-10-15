<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Queries;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Per-viewer badges for a page of jobs (tracking status, already viewed),
 * fetched in two queries whatever the page size.
 */
final readonly class ViewerJobState
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<int>  $jobIds
     * @return array{0: array<int, string>, 1: array<int, true>, 2: array<int, array{score: int, level: string, eligible: bool}>}
     */
    public function for(?User $user, array $jobIds): array
    {
        if ($user === null || $jobIds === []) {
            return [[], [], []];
        }

        /** @var array<int, string> $tracked */
        $tracked = $this->db->table('applications')
            ->where('user_id', $user->id)
            ->whereIn('job_listing_id', $jobIds)
            ->pluck('status', 'job_listing_id')
            ->map(fn (mixed $status): string => (string) $status)
            ->all();

        $viewed = array_fill_keys(
            $this->db->table('job_views')->where('user_id', $user->id)->whereIn('job_listing_id', $jobIds)->pluck('job_listing_id')->map(fn (mixed $id): int => (int) $id)->all(),
            true,
        );

        $matches = [];
        foreach ($this->db->table('job_matches')->where('user_id', $user->id)->whereIn('job_listing_id', $jobIds)->get(['job_listing_id', 'score', 'level', 'eligible']) as $row) {
            $matches[(int) $row->job_listing_id] = ['score' => (int) $row->score, 'level' => (string) $row->level, 'eligible' => (bool) $row->eligible];
        }

        return [$tracked, $viewed, $matches];
    }
}
