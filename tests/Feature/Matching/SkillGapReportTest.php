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

it('attaches courses to every missing skill, free ones first', function (): void {
    $user = User::factory()->create();
    matchWithGaps($user, 80, [['kubernetes', true], ['cobol-xyz', false]]);

    $skills = collect(app(SkillGapReport::class)->for($user->id)['skills'])->keyBy('skill');
    $prices = fn (string $slug): array => array_column($skills[$slug]['courses'], 'price');

    expect($skills['kubernetes']['courses'][0])->toMatchArray(['provider' => 'kubernetes.io', 'price' => 'free', 'curated' => true])
        ->and($prices('kubernetes'))->toBe(['free', 'free', 'free', 'free', 'paid', 'paid', 'paid'])
        // No hand-picked entry: search links, free sites first.
        ->and($skills['cobol-xyz']['courses'][0])->toMatchArray(['provider' => 'YouTube', 'price' => 'free', 'curated' => false])
        ->and($prices('cobol-xyz'))->toBe(['free', 'free', 'free', 'paid', 'paid']);
});

it('picks the jobs the person is nearest to meeting in full and what each one still needs', function (): void {
    $user = User::factory()->create();
    $far = matchWithGaps($user, 95, [['kubernetes', true], ['terraform', true], ['aws', false]]);
    $near = matchWithGaps($user, 60, [['terraform', true]]);
    $nearer = matchWithGaps($user, 55, [['terraform', false]]);
    matchWithGaps($user, 99, []);

    $closest = app(SkillGapReport::class)->for($user->id)['closest'];

    // Fewest missing first, then best score; a job with nothing missing is not a gap to close.
    expect(array_column($closest, 'id'))->toBe([$near->id, $nearer->id, $far->id])
        ->and($closest[0]['missing'][0])->toMatchArray(['skill' => 'terraform', 'required' => true])
        ->and($closest[0]['missing'][0]['courses'][0]['price'])->toBe('free')
        ->and(array_column($closest[2]['missing'], 'skill'))->toBe(['kubernetes', 'terraform', 'aws']);
});

it('sizes the study plan with a rough number of hours by skill type', function (): void {
    $user = User::factory()->create();
    matchWithGaps($user, 80, [['kubernetes', true], ['cobol-xyz', false], ['php', true]]);

    $skills = collect(app(SkillGapReport::class)->for($user->id)['skills'])->keyBy('skill');
    $closest = collect(app(SkillGapReport::class)->for($user->id)['closest'][0]['missing'])->keyBy('skill');

    // A language takes longer than a tool; unknown skills get the default.
    expect($skills['php']['hours'])->toBe(config('courses.hours.language'))
        ->and($skills['kubernetes']['hours'])->toBe(config('courses.hours.'.app(App\Modules\Profiles\Domain\Services\SkillCatalog::class)->type('kubernetes')))
        ->and($skills['cobol-xyz']['hours'])->toBe(config('courses.hours.default'))
        ->and($closest['php']['hours'])->toBe($skills['php']['hours']);
});
