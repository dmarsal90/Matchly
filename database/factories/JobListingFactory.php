<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Jobs\Domain\Services\CanonicalUrl;
use App\Modules\Jobs\Domain\Services\JobFingerprint;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Providers\Infrastructure\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobListing>
 */
final class JobListingFactory extends Factory
{
    protected $model = JobListing::class;

    public function definition(): array
    {
        $title = fake()->randomElement(['Senior Backend Engineer', 'Frontend Developer', 'Product Designer', 'DevOps Engineer', 'Data Scientist']);
        $company = fake()->company();
        $url = fake()->unique()->url();
        $postedAt = CarbonImmutable::now()->subHours(fake()->numberBetween(1, 240));

        return [
            'source_id' => fn (): int => Source::query()->firstOrCreate(
                ['key' => 'factory'],
                ['name' => 'Factory', 'homepage_url' => 'https://example.com'],
            )->id,
            'external_id' => fake()->unique()->uuid(),
            'title' => $title,
            'company' => $company,
            'description' => '<p>'.fake()->paragraph().'</p><p>'.fake()->paragraph().'</p>',
            'apply_url' => $url,
            'source_url' => $url,
            'canonical_url' => CanonicalUrl::from($url),
            'fingerprint' => JobFingerprint::make($title, $company),
            'content_hash' => hash('sha256', $title.$company),
            'category' => JobCategory::SoftwareDevelopment,
            'role' => JobRole::Backend,
            'work_mode' => WorkMode::Remote,
            'remote_scope' => RemoteScope::Worldwide,
            'allowed_regions' => [],
            'employment_type' => EmploymentType::FullTime,
            'seniority' => Seniority::Senior,
            'tags' => ['php', 'laravel'],
            'posted_at' => $postedAt,
            'first_seen_at' => $postedAt,
            'last_seen_at' => CarbonImmutable::now(),
            'status' => JobStatus::Open,
        ];
    }

    public function postedDaysAgo(int $days): self
    {
        return $this->state(fn (): array => [
            'posted_at' => CarbonImmutable::now()->subDays($days),
            'first_seen_at' => CarbonImmutable::now()->subDays($days),
        ]);
    }

    public function closed(): self
    {
        return $this->state(['status' => JobStatus::Closed, 'closed_at' => CarbonImmutable::now()]);
    }
}
