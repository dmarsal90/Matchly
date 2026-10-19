<?php

declare(strict_types=1);

use App\Modules\Jobs\Application\Queries\GhostJobCheck;
use App\Modules\Jobs\Infrastructure\Models\JobListing;

it('counts reposts weeks apart but not the same posting on another board', function (): void {
    $now = now();
    $job = JobListing::factory()->create(['fingerprint' => 'acme|dev', 'first_seen_at' => $now, 'posted_at' => $now]);

    // The same posting on another board, days apart: not a repost.
    JobListing::factory()->create(['fingerprint' => 'acme|dev', 'first_seen_at' => $now->subDays(2)]);
    expect(app(GhostJobCheck::class)->for($job))->toBeNull();

    JobListing::factory()->create(['fingerprint' => 'acme|dev', 'first_seen_at' => $now->subDays(30)]);
    JobListing::factory()->create(['fingerprint' => 'acme|dev', 'first_seen_at' => $now->subDays(60)]);

    expect(app(GhostJobCheck::class)->for($job))->toBe(['level' => 'medium', 'signals' => ['reposted']]);
});
