<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Queries;

use App\Modules\Jobs\Infrastructure\Models\JobListing;

/**
 * What similar open jobs in our own database pay: the same category and
 * seniority, the same currency, annualised. It is a reference built from the
 * listings that state a salary, not a market study, so it is only given with
 * enough of them to mean something.
 */
final readonly class SalaryBenchmark
{
    /** Fewer similar listings than this and no figure is shown. */
    public const int MIN_SAMPLE = 5;

    private const int MAX_SAMPLE = 500;

    /**
     * @return array{currency: string, low: int, median: int, high: int, sample: int}|null
     */
    public function for(JobListing $job): ?array
    {
        if ($job->category === null) {
            return null;
        }

        $listings = JobListing::query()
            ->visible()
            ->whereKeyNot($job->id)
            ->where('category', $job->category)
            ->when($job->seniority !== null, fn ($query) => $query->where('seniority', $job->seniority))
            ->when($job->salary_currency !== null, fn ($query) => $query->where('salary_currency', $job->salary_currency))
            ->whereNotNull('salary_currency')
            ->where(fn ($query) => $query->whereNotNull('salary_annual_min')->orWhereNotNull('salary_annual_max'))
            ->limit(self::MAX_SAMPLE)
            ->get(['salary_currency', 'salary_annual_min', 'salary_annual_max']);

        // Never mix currencies: the job's own, or else the most common one among the similar listings.
        $currency = $job->salary_currency ?? (string) $listings->countBy('salary_currency')->sortDesc()->keys()->first();
        $pay = $listings
            ->where('salary_currency', $currency)
            // The middle of the stated range; a one-sided range counts as its only figure.
            ->map(fn (JobListing $listing): float => (float) collect([$listing->salary_annual_min, $listing->salary_annual_max])->filter()->avg())
            ->sort()
            ->values();

        if ($pay->count() < self::MIN_SAMPLE) {
            return null;
        }

        $sorted = array_values($pay->all());

        return [
            'currency' => $currency,
            'low' => $this->percentile($sorted, 0.25),
            'median' => $this->percentile($sorted, 0.5),
            'high' => $this->percentile($sorted, 0.75),
            'sample' => count($sorted),
        ];
    }

    /**
     * @param  list<float>  $sorted
     */
    private function percentile(array $sorted, float $fraction): int
    {
        $position = ($sorted !== [] ? count($sorted) - 1 : 0) * $fraction;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        return (int) round($sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($position - $lower));
    }
}
