<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Providers\Infrastructure\Models\Source;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return list<int>
 */
function listedIds(string $query = ''): array
{
    $ids = [];
    test()->get('/jobs'.($query !== '' ? "?{$query}" : ''))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$ids): void {
            $page->component('jobs/Index');
            $ids = array_column($page->toArray()['props']['jobs']['data'], 'id');
        });

    return $ids;
}

it('is public', function (): void {
    JobListing::factory()->create();

    $this->get('/jobs')->assertOk();
});

it('lists newest first, falling back to first sighting, with a stable tie-breaker', function (): void {
    $old = JobListing::factory()->postedDaysAgo(5)->create();
    $undated = JobListing::factory()->create(['posted_at' => null, 'first_seen_at' => now()->subDays(2)]);
    $new = JobListing::factory()->postedDaysAgo(1)->create();
    $sameTimeA = JobListing::factory()->create(['posted_at' => now()->subDays(3), 'first_seen_at' => now()->subDays(3)]);
    $sameTimeB = JobListing::factory()->create(['posted_at' => $sameTimeA->posted_at, 'first_seen_at' => $sameTimeA->first_seen_at]);

    expect(listedIds())->toBe([$new->id, $undated->id, $sameTimeB->id, $sameTimeA->id, $old->id]);
});

it('never shows stale, closed, flagged or duplicate jobs', function (): void {
    $visible = JobListing::factory()->create();
    JobListing::factory()->postedDaysAgo(31)->create();
    JobListing::factory()->closed()->create();
    JobListing::factory()->create(['status' => JobStatus::Flagged]);
    JobListing::factory()->create(['duplicate_of_id' => $visible->id]);

    expect(listedIds())->toBe([$visible->id]);
});

it('filters by publication date presets and custom ranges', function (): void {
    $today = JobListing::factory()->create(['posted_at' => now()->subHours(3), 'first_seen_at' => now()->subHours(3)]);
    $fourDays = JobListing::factory()->postedDaysAgo(4)->create();
    $twentyDays = JobListing::factory()->postedDaysAgo(20)->create();

    expect(listedIds('posted=24h'))->toBe([$today->id])
        ->and(listedIds('posted=7d'))->toBe([$today->id, $fourDays->id])
        ->and(listedIds('from='.now()->subDays(21)->toDateString().'&to='.now()->subDays(3)->toDateString()))->toBe([$fourDays->id, $twentyDays->id]);
});

it('filters by every attribute', function (array $attributes, string $query): void {
    $match = JobListing::factory()->create($attributes);
    JobListing::factory()->create([
        'work_mode' => WorkMode::Hybrid, 'remote_scope' => RemoteScope::CountryRestricted, 'employment_type' => EmploymentType::Contract,
        'role' => JobRole::Designer, 'category' => JobCategory::Design, 'seniority' => Seniority::Junior, 'tags' => ['figma'],
    ]);

    expect(listedIds($query))->toBe([$match->id]);
})->with([
    'work mode' => [['work_mode' => WorkMode::Remote], 'work_mode=remote'],
    'remote scope' => [['remote_scope' => RemoteScope::Worldwide], 'scope=worldwide'],
    'employment type' => [['employment_type' => EmploymentType::FullTime], 'type=full_time'],
    'role' => [['role' => JobRole::Backend], 'role=backend'],
    'category (array syntax)' => [['category' => JobCategory::SoftwareDevelopment], 'category[]=software_development'],
    'seniority (comma syntax)' => [['seniority' => Seniority::Senior], 'seniority=senior,lead'],
    'tag' => [['tags' => ['php', 'laravel']], 'tag=laravel'],
]);

it('filters by source', function (): void {
    $remotive = Source::query()->create(['key' => 'remotive', 'name' => 'Remotive', 'homepage_url' => 'https://remotive.com']);
    $match = JobListing::factory()->create(['source_id' => $remotive->id]);
    JobListing::factory()->create();

    expect(listedIds('source=remotive'))->toBe([$match->id]);
});

it('filters by annualised minimum salary and currency', function (): void {
    $yearly = JobListing::factory()->create(['salary_min' => 70000, 'salary_max' => 90000, 'salary_currency' => 'EUR', 'salary_period' => SalaryPeriod::Year, 'salary_annual_min' => 70000, 'salary_annual_max' => 90000]);
    $hourly = JobListing::factory()->create(['salary_min' => 50, 'salary_max' => 60, 'salary_currency' => 'EUR', 'salary_period' => SalaryPeriod::Hour, 'salary_annual_min' => 104000, 'salary_annual_max' => 124800]);
    JobListing::factory()->create(['salary_annual_min' => 30000, 'salary_annual_max' => 40000, 'salary_currency' => 'EUR']);
    JobListing::factory()->create(['salary_annual_min' => 200000, 'salary_annual_max' => 250000, 'salary_currency' => 'USD']);

    expect(listedIds('salary_min=80000&currency=eur'))->toEqualCanonicalizing([$yearly->id, $hourly->id])
        ->and(listedIds('salary_min=80000&currency=EUR&sort=salary'))->toBe([$hourly->id, $yearly->id]);
});

it('searches title, company and tags', function (): void {
    $laravel = JobListing::factory()->create(['title' => 'Senior Laravel Engineer', 'company' => 'Acme']);
    $byCompany = JobListing::factory()->create(['title' => 'Backend Engineer', 'company' => 'Laravel Holdings']);
    JobListing::factory()->create(['title' => 'Product Designer', 'company' => 'Figma Fans', 'tags' => ['figma']]);

    expect(listedIds('q=laravel'))->toEqualCanonicalizing([$laravel->id, $byCompany->id])
        ->and(listedIds('q=laravel&sort=relevance')[0])->toBe($laravel->id);
});

it('ignores unknown or malformed filter values instead of failing', function (): void {
    $job = JobListing::factory()->create();

    expect(listedIds('work_mode=moon&posted=1y&sort=random&group=planet&per_page=999&from=yesterday&tag=<script>'))->toBe([$job->id]);
});

it('echoes the active filters for the URL state', function (): void {
    $this->get('/jobs?q=vue&work_mode=remote&posted=7d&sort=relevance&group=role&per_page=50')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'vue')
            ->where('filters.work_mode', ['remote'])
            ->where('filters.posted', '7d')
            ->where('filters.sort', 'relevance')
            ->where('filters.group', 'role')
            ->where('filters.per_page', 50));
});

it('falls back to newest when sorting by relevance without a query', function (): void {
    $this->get('/jobs?sort=relevance')->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'newest'));
});

it('groups by category with totals over the whole result, largest group first', function (): void {
    JobListing::factory()->count(3)->create(['category' => JobCategory::Design, 'role' => JobRole::Designer]);
    JobListing::factory()->count(5)->create(['category' => JobCategory::SoftwareDevelopment]);
    JobListing::factory()->create(['category' => null, 'role' => null]);

    $this->get('/jobs?group=category&per_page=10')->assertInertia(function (Assert $page): void {
        $page->where('groups', ['software_development' => 5, 'design' => 3, '' => 1]);
        $categories = array_column($page->toArray()['props']['jobs']['data'], 'category');
        expect($categories)->toBe([...array_fill(0, 5, 'software_development'), ...array_fill(0, 3, 'design'), null]);
    });
});

it('groups by source using source keys', function (): void {
    $wwr = Source::query()->create(['key' => 'weworkremotely', 'name' => 'We Work Remotely', 'homepage_url' => 'https://weworkremotely.com']);
    JobListing::factory()->count(2)->create(['source_id' => $wwr->id]);
    JobListing::factory()->create();

    $this->get('/jobs?group=source')->assertInertia(fn (Assert $page) => $page->where('groups', ['weworkremotely' => 2, 'factory' => 1]));
});

it('paginates on the server', function (): void {
    JobListing::factory()->count(12)->create();

    $this->get('/jobs?per_page=10&page=2')->assertInertia(fn (Assert $page) => $page
        ->has('jobs.data', 2)
        ->where('jobs.meta.total', 12)
        ->where('jobs.meta.current_page', 2)
        ->where('jobs.meta.last_page', 2));
});

it('credits the source and links to the original listing', function (): void {
    JobListing::factory()->create(['apply_url' => 'https://acme.test/apply', 'source_url' => 'https://remotive.com/job/1']);

    $this->get('/jobs')->assertInertia(fn (Assert $page) => $page
        ->where('jobs.data.0.apply_url', 'https://acme.test/apply')
        ->where('jobs.data.0.source.name', 'Factory')
        ->where('jobs.data.0.source.url', 'https://remotive.com/job/1'));
});

it('marks saved and viewed jobs for signed-in users without N+1 queries', function (): void {
    $user = User::factory()->create();
    $jobs = JobListing::factory()->count(15)->create();
    $this->actingAs($user)->post("/jobs/{$jobs[0]->id}/save");
    $this->actingAs($user)->get("/jobs/{$jobs[1]->id}");

    DB::enableQueryLog();
    $this->actingAs($user)->get('/jobs')->assertInertia(function (Assert $page) use ($jobs): void {
        $data = collect($page->toArray()['props']['jobs']['data'])->keyBy('id');
        expect($data[$jobs[0]->id]['tracking_status'])->toBe('saved')
            ->and($data[$jobs[1]->id]['viewed'])->toBeTrue()
            ->and($data[$jobs[2]->id]['tracking_status'])->toBeNull();
    });

    expect(count(DB::getQueryLog()))->toBeLessThan(15);
});

it('shows the job detail with its sanitized description and records the view', function (): void {
    $user = User::factory()->create();
    $job = JobListing::factory()->create(['description' => '<p>Build APIs</p>']);

    $this->actingAs($user)->get("/jobs/{$job->id}/some-slug")->assertInertia(fn (Assert $page) => $page
        ->component('jobs/Show')
        ->where('job.description', '<p>Build APIs</p>')
        ->where('isOpen', true));

    expect(DB::table('job_views')->where('user_id', $user->id)->where('job_listing_id', $job->id)->exists())->toBeTrue();
});

it('keeps closed jobs reachable but marked as no longer open', function (): void {
    $job = JobListing::factory()->closed()->create();

    $this->get("/jobs/{$job->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('isOpen', false));
});

it('redirects duplicates to the original posting', function (): void {
    $original = JobListing::factory()->create();
    $copy = JobListing::factory()->create(['duplicate_of_id' => $original->id]);

    $this->get("/jobs/{$copy->id}")->assertRedirect(route('jobs.show', $original->id))->assertStatus(301);
});

it('hides flagged jobs from everyone but admins', function (): void {
    $job = JobListing::factory()->create(['status' => JobStatus::Flagged]);

    $this->get("/jobs/{$job->id}")->assertNotFound();
    $this->actingAs(User::factory()->create())->get("/jobs/{$job->id}")->assertNotFound();
    $this->actingAs(User::factory()->create(['is_admin' => true]))->get("/jobs/{$job->id}")->assertOk();
});
