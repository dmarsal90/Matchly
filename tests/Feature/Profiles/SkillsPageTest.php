<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\ExportPersonalData;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Domain\Enums\OAuthProvider;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

function userWithSkills(): User
{
    $user = User::factory()->create();
    $user->profile()->create(['target_roles' => ['backend'], 'salary_min' => 60000, 'salary_currency' => 'EUR', 'salary_period' => SalaryPeriod::Year])
        ->forceFill(['onboarding_completed_at' => now()])->save();
    $user->skills()->create(['source' => SkillSource::Declared, 'skill' => 'react', 'name' => 'React', 'level' => 5]);
    $user->skills()->create(['source' => SkillSource::Github, 'skill' => 'react', 'name' => 'React', 'level' => 2, 'evidence' => ['repository_count' => 1]]);
    $user->skills()->create(['source' => SkillSource::Declared, 'skill' => 'php', 'name' => 'PHP', 'level' => 4]);

    return $user;
}

it('shows the merged skills, sources and search profile', function (): void {
    $user = userWithSkills();
    app(GrantConsent::class)->handle($user, ConsentPurpose::GithubImport);
    $user->profileImports()->create(['source' => SkillSource::Github, 'status' => ImportStatus::Confirmed, 'reference' => 'octocat', 'payload' => ['skills' => [['skill' => 'react']]], 'confirmed_at' => now()]);
    $user->profileImports()->create(['source' => SkillSource::Linkedin, 'status' => ImportStatus::Failed, 'error' => 'unsafe_archive']);
    $this->actingAs($user)->post('/profile/search/regenerate');

    $this->get('/profile/skills')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('skills/Index')
            ->where('onboardingCompleted', true)
            ->where('skills.0.skill', 'react')
            ->where('skills.0.sources', ['declared', 'github'])
            ->where('skills.0.level', 5)
            ->where('skills.0.level_gap', 3)
            ->where('skills.0.has_evidence', true)
            ->where('skills.1.has_evidence', false)
            ->where('sources.github.skills_count', 1)
            ->where('sources.github.consented', true)
            ->where('sources.github.confirmed.reference', 'octocat')
            ->where('sources.github.confirmed.skills_count', 1)
            ->where('sources.linkedin.pending.error', 'unsafe_archive')
            ->where('sources.linkedin.consented', false)
            ->where('sources.declared.consented', true)
            ->where('searchProfile.roles', ['backend'])
            ->where('searchProfile.technology_names', ['React', 'PHP'])
            ->where('searchProfile.listing_query', ['role' => 'backend', 'salary_min' => '60000', 'currency' => 'EUR'])
            ->where('github.oauth_available', false)
            ->has('options.linkedinExportUrl'));
});

it('lets the user edit the search profile and keeps it on later changes', function (): void {
    $user = userWithSkills();

    $this->actingAs($user)->put('/profile/search', [
        'roles' => ['fullstack'],
        'technologies' => ['Vue.js', 'vue', 'Unreal Engine'],
        'seniorities' => ['senior'],
        'work_modes' => ['remote'],
        'employment_types' => [],
        'salary_min' => 70000,
        'salary_currency' => 'USD',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $expected = [
        'roles' => ['fullstack'], 'technologies' => ['vue', 'unreal-engine'], 'seniorities' => ['senior'],
        'work_modes' => ['remote'], 'employment_types' => [], 'salary_min' => 70000, 'salary_currency' => 'USD', 'edited' => true,
    ];
    expect($user->profile()->sole()->search_profile)->toBe($expected);

    // A later profile change does not overwrite the user's version…
    $this->put('/onboarding/role', ['target_roles' => ['qa'], 'seniority' => 'mid', 'years_experience' => 2]);
    expect($user->profile()->sole()->search_profile)->toBe($expected);

    // …until they ask for a new suggestion.
    $this->post('/profile/search/regenerate')->assertRedirect();
    expect($user->profile()->sole()->search_profile)->toMatchArray(['roles' => ['qa'], 'edited' => false]);
});

it('validates the search profile', function (): void {
    $this->actingAs(userWithSkills())->put('/profile/search', [
        'roles' => ['astronaut'], 'technologies' => [], 'seniorities' => [], 'work_modes' => [], 'employment_types' => [],
        'salary_min' => 50000,
    ])->assertSessionHasErrors(['roles.0', 'salary_currency']);
});

it('offers onboarding and the search profile on the job listing', function (): void {
    $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->where('viewer', null));

    $newcomer = User::factory()->create();
    $this->actingAs($newcomer)->get('/jobs')->assertInertia(fn (Assert $page) => $page
        ->where('viewer.onboarding_completed', false)
        ->where('viewer.search_query', null));

    $user = userWithSkills();
    $this->actingAs($user)->post('/profile/search/regenerate');
    $this->get('/jobs')->assertInertia(fn (Assert $page) => $page
        ->where('viewer.onboarding_completed', true)
        ->where('viewer.search_query.role', 'backend'));
});

it('returns to the skills page after linking GitHub', function (): void {
    config(['services.github.client_id' => 'id', 'services.github.client_secret' => 'secret']);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/auth/github/redirect?return=skills')->assertRedirect();
    expect(session('oauth.return'))->toBe('skills.index');

    // Unknown targets are ignored (no open redirect).
    $this->get('/auth/github/redirect?return=https://evil.test')->assertRedirect();
    expect(session('oauth.return'))->toBeNull();
});

it('exports skills and imports with the personal data', function (): void {
    $user = userWithSkills();
    $user->profileImports()->create(['source' => SkillSource::Linkedin, 'status' => ImportStatus::Confirmed, 'payload' => ['positions' => [['title' => 'Engineer']]], 'confirmed_at' => now()]);

    $zip = new ZipArchive;
    $zip->open(app(ExportPersonalData::class)->handle($user));
    $data = json_decode((string) $zip->getFromName('data.json'), true);

    expect($data['sections']['skills']['skills'])->toHaveCount(3)
        ->and($data['sections']['skills']['imports'][0]['data']['positions'][0]['title'])->toBe('Engineer')
        ->and($data['sections']['profile']['profile']['target_roles'])->toBe(['backend']);
});

it('discards imports nobody reviewed after the retention period', function (): void {
    $user = User::factory()->create();
    $old = $user->profileImports()->create(['source' => SkillSource::Github, 'status' => ImportStatus::PendingReview, 'payload' => []]);
    $old->forceFill(['updated_at' => CarbonImmutable::now()->subDays(31)])->saveQuietly();
    $fresh = $user->profileImports()->create(['source' => SkillSource::Linkedin, 'status' => ImportStatus::PendingReview, 'payload' => []]);
    $confirmed = $user->profileImports()->create(['source' => SkillSource::Cv, 'status' => ImportStatus::Confirmed, 'payload' => []]);
    $confirmed->forceFill(['updated_at' => CarbonImmutable::now()->subYears(2)])->saveQuietly();

    $this->artisan('privacy:prune')->assertSuccessful();

    expect(ProfileImport::query()->pluck('id')->all())->toEqualCanonicalizing([$fresh->id, $confirmed->id]);
});

it('keeps the GitHub OAuth link visible only when GitHub login is configured', function (): void {
    config(['services.github.client_id' => 'id', 'services.github.client_secret' => 'secret']);
    $user = User::factory()->create();
    $user->socialAccounts()->create(['provider' => OAuthProvider::Github, 'provider_user_id' => '9', 'nickname' => 'octocat']);

    $this->actingAs($user)->get('/profile/skills')->assertInertia(fn (Assert $page) => $page
        ->where('github.oauth_available', true)
        ->where('github.linked_username', 'octocat')
        ->where('github.suggested_username', 'octocat'));
});
