<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Infrastructure\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function profilePayload(array $overrides = []): array
{
    return [
        'headline' => 'Backend developer, 6 years of PHP',
        'phone' => '+34 600 123 456',
        'country' => 'ES',
        'city' => 'Valencia',
        'github_username' => 'octo-cat',
        'linkedin_url' => 'https://www.linkedin.com/in/octocat',
        'portfolio_url' => 'https://octo.dev',
        'languages' => [['code' => 'es', 'level' => 'native'], ['code' => 'en', 'level' => 'C1']],
        'salary_min' => 50000,
        'salary_currency' => 'EUR',
        'salary_period' => 'year',
        'work_modes' => ['remote'],
        'employment_types' => ['full_time', 'contract'],
        ...$overrides,
    ];
}

it('requires a verified account', function (): void {
    $this->get('/profile')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get('/profile')->assertRedirect(route('verification.notice'));
});

it('shows the career profile form with its options', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->component('profile/Edit')
            ->where('profile.headline', null)
            ->has('options.countries.ES')
            ->where('options.workModes', ['remote', 'hybrid', 'onsite'])
            ->has('cvDocuments', 0));
});

it('saves the profile and announces the change', function (): void {
    Event::fake([ProfileChanged::class]);
    $user = User::factory()->create();

    $this->actingAs($user)->put('/profile', profilePayload())->assertRedirect('/profile')->assertSessionHasNoErrors();

    $profile = Profile::query()->where('user_id', $user->id)->sole();
    expect($profile->country)->toBe('ES')
        ->and($profile->languages)->toBe([['code' => 'es', 'level' => 'native'], ['code' => 'en', 'level' => 'C1']])
        ->and($profile->preferences['employment_types'])->toBe(['full_time', 'contract'])
        ->and($profile->phone)->toBe('+34 600 123 456');
    Event::assertDispatched(ProfileChanged::class, fn (ProfileChanged $event): bool => $event->userId === $user->id);
});

it('stores the phone number encrypted', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/profile', profilePayload());

    expect(DB::table('profiles')->where('user_id', $user->id)->value('phone'))->not->toContain('600');
});

it('does not announce a change when nothing changed', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->put('/profile', profilePayload());

    Event::fake([ProfileChanged::class]);
    $this->actingAs($user)->put('/profile', profilePayload());

    Event::assertNotDispatched(ProfileChanged::class);
});

it('validates profile fields', function (array $overrides, string $field): void {
    $this->actingAs(User::factory()->create())
        ->put('/profile', profilePayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'unknown country' => [['country' => 'XX'], 'country'],
    'invalid github username' => [['github_username' => '-bad--name-'], 'github_username'],
    'not a linkedin url' => [['linkedin_url' => 'https://evil.example.com/in/me'], 'linkedin_url'],
    'insecure portfolio' => [['portfolio_url' => 'http://octo.dev'], 'portfolio_url'],
    'bad phone' => [['phone' => 'call me maybe'], 'phone'],
    'duplicate language' => [['languages' => [['code' => 'en', 'level' => 'B2'], ['code' => 'en', 'level' => 'C1']]], 'languages.1.code'],
    'unknown level' => [['languages' => [['code' => 'en', 'level' => 'fluent']]], 'languages.0.level'],
    'salary without currency' => [['salary_currency' => null], 'salary_currency'],
    'unknown work mode' => [['work_modes' => ['moon']], 'work_modes.0'],
]);

it('never asks for sensitive attributes', function (): void {
    $columns = DB::getSchemaBuilder()->getColumnListing('profiles');

    expect($columns)->not->toContain('age', 'birth_date', 'gender', 'nationality', 'photo', 'avatar');
});
