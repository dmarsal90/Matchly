<?php

declare(strict_types=1);

use App\Modules\Jobs\Application\Queries\SalaryBenchmark;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Inertia\Testing\AssertableInertia as Assert;

function paidJob(int $min, int $max, string $currency = 'EUR', array $state = []): JobListing
{
    return JobListing::factory()->create([...$state, 'salary_currency' => $currency, 'salary_annual_min' => $min, 'salary_annual_max' => $max]);
}

it('gives the spread of what similar listings pay once there are enough of them', function (): void {
    foreach ([40, 50, 60, 70, 80] as $thousand) {
        paidJob($thousand * 1000 - 5000, $thousand * 1000 + 5000);
    }
    $job = JobListing::factory()->create();

    expect(app(SalaryBenchmark::class)->for($job))->toBe(['currency' => 'EUR', 'low' => 50000, 'median' => 60000, 'high' => 70000, 'sample' => 5]);
});

it('shows nothing with too few listings, and never mixes categories, levels or currencies', function (): void {
    foreach ([40, 50, 60, 70] as $thousand) {
        paidJob($thousand * 1000, $thousand * 1000);
    }
    // Same shape but different on one axis each: none of them may count.
    paidJob(90000, 90000, 'USD');
    paidJob(90000, 90000, 'EUR', ['category' => JobCategory::Design]);
    paidJob(90000, 90000, 'EUR', ['seniority' => Seniority::Junior]);
    $job = JobListing::factory()->create(['salary_currency' => 'EUR']);

    expect(app(SalaryBenchmark::class)->for($job))->toBeNull();
});

it('reaches the job page for a listing without a salary of its own', function (): void {
    foreach ([40, 50, 60, 70, 80] as $thousand) {
        paidJob($thousand * 1000, $thousand * 1000);
    }
    $job = JobListing::factory()->create();

    $this->get("/jobs/{$job->id}")->assertInertia(fn (Assert $page) => $page->where('salaryBenchmark.median', 60000)->where('salaryBenchmark.sample', 5));
});
