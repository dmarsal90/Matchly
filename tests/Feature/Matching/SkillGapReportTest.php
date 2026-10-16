<?php

declare(strict_types=1);

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\Queries\SkillGapReport;
use App\Modules\Matching\Infrastructure\Models\JobMatch;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  list<array{string, bool}>  $missing  [slug, required]
 */
function matchWithGaps(User $user, int $score, array $missing, bool $eligible = true, ?JobListing $job = null): JobListing
{
    $job ??= JobListing::factory()->create();
    JobMatch::query()->create([
        'user_id' => $user->id,
        'job_listing_id' => $job->id,
        'score' => $score,
        'level' => 'medium',
        'eligible' => $eligible,
        'result' => ['missing_skills' => array_map(fn (array $skill): array => ['skill' => $skill[0], 'name' => ucfirst($skill[0]), 'required' => $skill[1]], $missing)],
        'computed_at' => now(),
    ]);

    return $job;
}

it('ranks missing skills by the jobs they would open up', function (): void {
    $user = User::factory()->create();
    matchWithGaps($user, 80, [['kubernetes', true]]);
    matchWithGaps($user, 70, [['kubernetes', true], ['terraform', true]]);
    matchWithGaps($user, 60, [['terraform', false]]);
    matchWithGaps($user, 40, [['rust', true]]);

    $report = app(SkillGapReport::class)->for($user->id);

    // Rust is below the score threshold and untracked: ignored.
    expect($report['jobs'])->toBe(3)
        ->and(array_column($report['skills'], 'skill'))->toBe(['kubernetes', 'terraform'])
        ->and($report['skills'][0])->toMatchArray(['jobs' => 2, 'required' => 2, 'only_gap' => 1])
        ->and($report['skills'][1])->toMatchArray(['jobs' => 2, 'required' => 1, 'only_gap' => 0]);
});

it('includes tracked jobs even when their score is low, but never ineligible or closed ones', function (): void {
    $user = User::factory()->create();
    $tracked = matchWithGaps($user, 20, [['rust', true]]);
    (new Application)->forceFill(['user_id' => $user->id, 'job_listing_id' => $tracked->id, 'status' => ApplicationStatus::Saved, 'status_changed_at' => now()])->save();
    matchWithGaps($user, 90, [['golang', true]], eligible: false);
    matchWithGaps($user, 90, [['swift', true]], job: JobListing::factory()->create(['status' => 'closed']));

    $report = app(SkillGapReport::class)->for($user->id);

    expect(array_column($report['skills'], 'skill'))->toBe(['rust'])
        ->and($report['tracked'])->toBe(1);
});

it('never mixes in other people’s matches', function (): void {
    $user = User::factory()->create();
    matchWithGaps(User::factory()->create(), 90, [['kubernetes', true]]);

    expect(app(SkillGapReport::class)->for($user->id)['skills'])->toBe([]);
});

it('shows the insights page to signed-in users only', function (): void {
    $this->get('/insights')->assertRedirect(route('login'));

    $user = User::factory()->create();
    matchWithGaps($user, 80, [['kubernetes', true]]);

    $this->actingAs($user)->get('/insights')->assertInertia(fn (Assert $page) => $page
        ->component('insights/Index')
        ->where('skillGaps.skills.0.skill', 'kubernetes'));
});
