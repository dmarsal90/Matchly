<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Application\Events\JobsIngested;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\Actions\ScoreJobsForUser;
use App\Modules\Matching\Infrastructure\Models\JobMatch;
use App\Modules\Matching\Infrastructure\Queue\RecalculateMatchesJob;
use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function laravelDeveloper(): User
{
    $user = User::factory()->create();
    $user->profile()->create(['target_roles' => ['backend'], 'seniority' => Seniority::Senior, 'years_experience' => 7, 'country' => 'ES', 'languages' => [['code' => 'en', 'level' => 'C1']]])
        ->forceFill(['onboarding_completed_at' => now()])->save();
    $user->skills()->create(['source' => SkillSource::Github, 'skill' => 'php', 'name' => 'PHP', 'level' => 5, 'years' => 8]);
    $user->skills()->create(['source' => SkillSource::Github, 'skill' => 'laravel', 'name' => 'Laravel', 'level' => 5, 'years' => 6]);
    $user->skills()->create(['source' => SkillSource::Declared, 'skill' => 'postgresql', 'name' => 'PostgreSQL', 'level' => 4]);

    return $user;
}

function laravelJob(array $attributes = []): JobListing
{
    return JobListing::factory()->create([
        'title' => 'Senior Laravel Developer',
        'description' => '<p>Requirements:</p><ul><li>5+ years of experience</li><li>PHP and Laravel</li><li>PostgreSQL</li><li>Fluent English</li></ul>',
        'tags' => [],
        ...$attributes,
    ]);
}

it('scores visible jobs for a user and stores the explanation', function (): void {
    $user = laravelDeveloper();
    $good = laravelJob();
    $design = JobListing::factory()->create(['title' => 'Product Designer', 'role' => JobRole::Designer, 'description' => '<p>Figma and user research.</p>', 'tags' => []]);
    $closed = laravelJob(['status' => 'closed']);

    expect(app(ScoreJobsForUser::class)->handle($user))->toBe(2);

    $matches = JobMatch::query()->where('user_id', $user->id)->get()->keyBy('job_listing_id');
    expect($matches)->toHaveCount(2)
        ->and($matches->has($closed->id))->toBeFalse()
        ->and($matches[$good->id]->score)->toBeGreaterThan($matches[$design->id]->score)
        ->and($matches[$good->id]->level)->toBe('high')
        ->and($matches[$good->id]->result['matched_skills'])->toHaveCount(3)
        // The job's requirements were extracted (rules) and stored once.
        ->and($good->fresh()->requirements['source'])->toBe('rules');
});

it('removes scores when the profile no longer has anything to score', function (): void {
    $user = laravelDeveloper();
    laravelJob();
    app(ScoreJobsForUser::class)->handle($user);

    $user->skills()->delete();
    $user->profile()->update(['target_roles' => '[]', 'onboarding_completed_at' => null]);
    app(ScoreJobsForUser::class)->handle($user->fresh());

    expect(JobMatch::query()->count())->toBe(0);
});

it('rescores in the background when the profile changes', function (): void {
    Queue::fake();
    $user = laravelDeveloper();

    event(new ProfileChanged($user->id));

    Queue::assertPushedOn('matching', RecalculateMatchesJob::class, fn (RecalculateMatchesJob $job): bool => $job->userId === $user->id);
});

it('scores newly ingested jobs for every scorable user', function (): void {
    $user = laravelDeveloper();
    User::factory()->create(); // no profile: not scored
    $job = laravelJob();

    event(new JobsIngested('remotive', [$job->id]));

    expect(JobMatch::query()->pluck('user_id')->all())->toBe([$user->id])
        ->and($job->fresh()->requirements)->not->toBeNull();
});

it('re-extracts requirements of jobs whose content changed', function (): void {
    laravelDeveloper();
    $job = laravelJob(['requirements' => ['source' => 'ai', 'required_skills' => [['skill' => 'cobol', 'name' => 'COBOL', 'min_years' => null]]]]);

    event(new JobsIngested('remotive', [], [$job->id]));

    expect(array_column($job->fresh()->requirements['required_skills'], 'skill'))->not->toContain('cobol');
});

it('shows scores on cards and offers a recommended tab ordered by score', function (): void {
    $user = laravelDeveloper();
    $best = laravelJob(['posted_at' => now()->subDays(3)]);
    $weak = JobListing::factory()->create(['title' => 'Data Scientist', 'role' => JobRole::DataScientist, 'description' => '<p>Python, pandas, TensorFlow.</p>', 'tags' => [], 'posted_at' => now()->subHour()]);
    $usOnly = laravelJob(['remote_scope' => RemoteScope::CountryRestricted, 'allowed_regions' => ['US'], 'description' => '<p>US residents only. PHP and Laravel.</p>', 'posted_at' => now()]);
    app(ScoreJobsForUser::class)->handle($user);

    $this->actingAs($user)->get('/jobs')->assertInertia(fn (Assert $page) => $page
        ->where('viewer.has_matches', true)
        ->where('jobs.data.0.id', $usOnly->id)
        ->where('jobs.data.0.match.eligible', false));

    $this->get('/jobs?tab=recommended')->assertInertia(fn (Assert $page) => $page
        ->where('filters.tab', 'recommended')
        ->where('filters.sort', 'match')
        ->where('jobs.meta.total', 2)
        ->where('jobs.data.0.id', $best->id)
        ->where('jobs.data.1.id', $weak->id)
        ->where('jobs.data.0.match.level', 'high'));

    $this->get('/jobs?min_score=70')->assertInertia(fn (Assert $page) => $page
        ->where('filters.min_score', 70)
        ->where('jobs.data', fn ($jobs) => collect($jobs)->every(fn (array $job): bool => $job['match']['score'] >= 70)));

    $this->get('/jobs?sort=match')->assertInertia(fn (Assert $page) => $page->where('jobs.data.0.match.score', fn (int $score) => $score === JobMatch::query()->max('score')));
});

it('ignores score features for guests', function (): void {
    laravelJob();

    $this->get('/jobs?tab=recommended&sort=match&min_score=90')->assertInertia(fn (Assert $page) => $page
        ->where('filters.tab', null)
        ->where('filters.sort', 'newest')
        ->where('filters.min_score', null)
        ->where('jobs.meta.total', 1)
        ->where('jobs.data.0.match', null));
});

it('explains the score and the requirements on the job page', function (): void {
    $user = laravelDeveloper();
    $job = laravelJob();

    // Not scored yet: computed on the spot.
    $this->actingAs($user)->get("/jobs/{$job->id}")->assertInertia(fn (Assert $page) => $page
        ->component('jobs/Show')
        ->where('requirements.source', 'rules')
        ->where('requirements.min_years_experience', 5)
        ->where('match.eligible', true)
        ->has('match.breakdown.skills')
        ->where('match.matched_skills.2.evidenced', false)
        ->where('match.suggestions.0.key', 'add_evidence'));
});

it('shows requirements but no score to guests', function (): void {
    $job = laravelJob();

    $this->get("/jobs/{$job->id}")->assertInertia(fn (Assert $page) => $page
        ->has('requirements.required_skills')
        ->where('match', null));
});

it('queues the AI refinement lazily when a job is opened', function (): void {
    Queue::fake();
    config(['ai.job_requirements' => 'lazy']);
    $job = laravelJob();

    $this->get("/jobs/{$job->id}");
    Queue::assertPushedOn('ai', App\Modules\Matching\Infrastructure\Queue\RefineJobRequirementsJob::class);

    config(['ai.job_requirements' => 'off']);
    Queue::fake();
    $this->get("/jobs/{$job->id}");
    Queue::assertNothingPushed();
});

it('queues a rescore for everyone from the console', function (): void {
    Queue::fake();
    laravelDeveloper();
    laravelDeveloper();

    $this->artisan('matching:recalculate')->assertSuccessful();

    Queue::assertPushed(RecalculateMatchesJob::class, 2);
});
