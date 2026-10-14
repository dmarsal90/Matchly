<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\UserSkill;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

it('requires a verified account', function (): void {
    $this->get('/onboarding')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->put('/onboarding/role')->assertRedirect(route('verification.notice'));
});

it('shows the requested step with saved answers and options', function (): void {
    $user = User::factory()->create();
    $user->profile()->create(['target_roles' => ['backend'], 'seniority' => Seniority::Senior]);
    $user->skills()->create(['source' => SkillSource::Declared, 'skill' => 'php', 'name' => 'PHP', 'level' => 5]);

    $this->actingAs($user)->get('/onboarding?step=skills')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('onboarding/Questionnaire')
            ->where('step', 'skills')
            ->where('steps', ['role', 'skills', 'languages', 'location', 'conditions'])
            ->where('completed', false)
            ->where('answers.target_roles', ['backend'])
            ->where('answers.seniority', 'senior')
            ->where('answers.skills.0', ['name' => 'PHP', 'level' => 5, 'years' => null, 'last_used_year' => null])
            ->has('options.skillSuggestions')
            ->where('options.maxSkills', 40));
});

it('falls back to the first step for unknown steps', function (): void {
    $this->actingAs(User::factory()->create())->get('/onboarding?step=nope')
        ->assertInertia(fn (Assert $page) => $page->where('step', 'role'));
});

it('saves each step on its own and moves to the next one', function (): void {
    Event::fake([ProfileChanged::class]);
    $user = User::factory()->create();

    $this->actingAs($user)->put('/onboarding/role', [
        'target_roles' => ['backend', 'fullstack'],
        'seniority' => 'senior',
        'years_experience' => 7,
    ])->assertRedirect(route('onboarding.show', ['step' => 'skills']));

    $profile = $user->profile()->sole();
    expect($profile->target_roles)->toBe(['backend', 'fullstack'])
        ->and($profile->seniority)->toBe(Seniority::Senior)
        ->and($profile->years_experience)->toBe(7)
        ->and($profile->onboarding_completed_at)->toBeNull();
    Event::assertDispatched(ProfileChanged::class);
});

it('replaces declared skills, normalising synonyms', function (): void {
    $user = User::factory()->create();
    $user->skills()->create(['source' => SkillSource::Declared, 'skill' => 'cobol', 'name' => 'COBOL', 'level' => 2]);
    $user->skills()->create(['source' => SkillSource::Github, 'skill' => 'php', 'name' => 'PHP', 'level' => 4]);

    $this->actingAs($user)->put('/onboarding/skills', ['skills' => [
        ['name' => 'Vue.js', 'level' => 3, 'years' => 2, 'last_used_year' => 2025],
        ['name' => 'php', 'level' => 5, 'years' => '8.5', 'last_used_year' => null],
        ['name' => 'VueJS', 'level' => 4, 'years' => null, 'last_used_year' => null],
        ['name' => 'Unreal Engine', 'level' => 2],
    ]])->assertRedirect(route('onboarding.show', ['step' => 'languages']))->assertSessionHasNoErrors();

    $declared = UserSkill::query()->where('source', SkillSource::Declared)->orderBy('skill')->get();
    expect($declared->pluck('skill')->all())->toBe(['php', 'unreal-engine', 'vue'])
        ->and($declared->firstWhere('skill', 'vue')?->level)->toBe(4)
        ->and($declared->firstWhere('skill', 'vue')?->name)->toBe('Vue')
        ->and($declared->firstWhere('skill', 'php')?->years)->toBe(8.5)
        // Evidence from other sources is untouched.
        ->and(UserSkill::query()->where('source', SkillSource::Github)->count())->toBe(1);
});

it('validates each step', function (string $step, array $payload, array $errors): void {
    $this->actingAs(User::factory()->create())->put("/onboarding/{$step}", $payload)->assertSessionHasErrors($errors);
})->with([
    'role without roles' => ['role', ['target_roles' => [], 'seniority' => 'senior', 'years_experience' => 3], ['target_roles']],
    'too many roles' => ['role', ['target_roles' => ['backend', 'frontend', 'qa', 'devops'], 'seniority' => 'mid', 'years_experience' => 3], ['target_roles']],
    'unknown seniority' => ['role', ['target_roles' => ['backend'], 'seniority' => 'wizard', 'years_experience' => 3], ['seniority']],
    'skill level out of range' => ['skills', ['skills' => [['name' => 'PHP', 'level' => 6]]], ['skills.0.level']],
    'skill used in the future' => ['skills', ['skills' => [['name' => 'PHP', 'level' => 3, 'last_used_year' => 2999]]], ['skills.0.last_used_year']],
    'duplicate language' => ['languages', ['languages' => [['code' => 'en', 'level' => 'C1'], ['code' => 'en', 'level' => 'B2']]], ['languages.0.code']],
    'inverted time zones' => ['location', ['regions' => [], 'utc_offset_min' => 3, 'utc_offset_max' => -2, 'work_modes' => [], 'employment_types' => []], ['utc_offset_max']],
    'unknown region' => ['location', ['regions' => ['MARS'], 'work_modes' => [], 'employment_types' => []], ['regions.0']],
    'salary without currency' => ['conditions', ['salary_min' => 50000, 'dealbreakers' => []], ['salary_currency', 'salary_period']],
    'unknown dealbreaker' => ['conditions', ['dealbreakers' => ['no_meetings']], ['dealbreakers.0']],
]);

it('rejects unknown steps', function (): void {
    $this->actingAs(User::factory()->create())->put('/onboarding/salary', [])->assertNotFound();
});

it('completes onboarding on the last step and builds a search profile', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->put('/onboarding/role', ['target_roles' => ['backend'], 'seniority' => 'senior', 'years_experience' => 6]);
    $this->put('/onboarding/skills', ['skills' => [['name' => 'Laravel', 'level' => 5], ['name' => 'PostgreSQL', 'level' => 4]]]);
    $this->put('/onboarding/languages', ['languages' => [['code' => 'es', 'level' => 'native']]]);
    $this->put('/onboarding/location', ['regions' => ['EU'], 'utc_offset_min' => -1, 'utc_offset_max' => 3, 'work_modes' => ['remote'], 'employment_types' => ['full_time']]);
    $this->put('/onboarding/conditions', [
        'salary_min' => 4000, 'salary_currency' => 'EUR', 'salary_period' => 'month',
        'dealbreakers' => ['remote_only', 'salary_floor'], 'dealbreaker_notes' => '  No on-call  ',
    ])->assertRedirect(route('skills.index'))->assertSessionHas('inertia.flash_data.toast.message', __('onboarding.completed'));

    $profile = $user->profile()->sole();
    expect($profile->onboarding_completed_at)->not->toBeNull()
        ->and($profile->regions)->toBe(['EU'])
        ->and([$profile->utc_offset_min, $profile->utc_offset_max])->toBe([-1, 3])
        ->and($profile->preferences['work_modes'])->toBe(['remote'])
        ->and($profile->salary_currency)->toBe('EUR')
        ->and($profile->salary_period)->toBe(SalaryPeriod::Month)
        ->and($profile->dealbreakers)->toBe(['remote_only', 'salary_floor'])
        ->and($profile->dealbreaker_notes)->toBe('No on-call')
        ->and($profile->search_profile)->toMatchArray([
            'roles' => ['backend'],
            'technologies' => ['laravel', 'postgresql'],
            'work_modes' => ['remote'],
            'employment_types' => ['full_time'],
            'salary_min' => 48000,
            'salary_currency' => 'EUR',
            'edited' => false,
        ]);

    // Saving the last step again is an edit, not a first completion.
    $this->put('/onboarding/conditions', ['dealbreakers' => []])->assertSessionHas('inertia.flash_data.toast.message', __('onboarding.saved'));
});
