<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Actions;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class RecordJobView
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(User $user, JobListing $job): void
    {
        $this->db->table('job_views')->upsert(
            [['user_id' => $user->id, 'job_listing_id' => $job->id, 'viewed_at' => CarbonImmutable::now()]],
            ['user_id', 'job_listing_id'],
            ['viewed_at'],
        );
    }
}
