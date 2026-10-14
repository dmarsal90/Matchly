<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\SocialAccount;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function (): void {
    config([
        'services.github.client_id' => 'id',
        'services.github.client_secret' => 'secret',
        'services.github.redirect' => 'http://localhost/auth/github/callback',
    ]);
});

function githubIdentity(array $overrides = []): SocialiteUser
{
    return (new SocialiteUser)->map([
        'id' => '4242',
        'nickname' => 'octocat',
        'name' => 'Mona Lisa',
        'email' => 'mona@example.com',
        ...$overrides,
    ])->setToken('gho_token');
}

it('hides providers without credentials', function (): void {
    $this->get('/auth/google/redirect')->assertNotFound();
});

it('creates a verified account on first sign-in', function (): void {
    Socialite::fake('github', githubIdentity());

    $this->get('/auth/github/callback')->assertRedirect(route('dashboard'));

    $user = User::query()->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->email)->toBe('mona@example.com')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->password)->toBeNull()
        ->and(SocialAccount::query()->sole()->nickname)->toBe('octocat');
});

it('stores provider tokens encrypted', function (): void {
    Socialite::fake('github', githubIdentity());

    $this->get('/auth/github/callback');

    expect(DB::table('social_accounts')->value('token'))->not->toBe('gho_token')
        ->and(SocialAccount::query()->sole()->token)->toBe('gho_token');
});

it('signs returning users into the linked account', function (): void {
    Socialite::fake('github', githubIdentity());
    $this->get('/auth/github/callback');
    auth()->logout();

    Socialite::fake('github', githubIdentity(['email' => 'changed@example.com']));
    $this->get('/auth/github/callback');

    expect(User::query()->count())->toBe(1);
    $this->assertAuthenticatedAs(User::query()->sole());
});

it('links to an existing verified account with the same e-mail', function (): void {
    $existing = User::factory()->create(['email' => 'mona@example.com']);
    Socialite::fake('github', githubIdentity());

    $this->get('/auth/github/callback');

    $this->assertAuthenticatedAs($existing);
    expect($existing->refresh()->password)->not->toBeNull();
});

it('takes over an unverified look-alike account safely (pre-hijacking guard)', function (): void {
    $squatter = User::factory()->unverified()->create(['email' => 'mona@example.com', 'password' => 'attacker-password']);
    DB::table('sessions')->insert(['id' => 'attacker', 'user_id' => $squatter->id, 'payload' => '', 'last_activity' => time()]);
    Socialite::fake('github', githubIdentity());

    $this->get('/auth/github/callback');

    $squatter->refresh();
    expect($squatter->password)->toBeNull()
        ->and($squatter->hasVerifiedEmail())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'attacker')->exists())->toBeFalse();
});

it('links a provider to the signed-in account', function (): void {
    $user = User::factory()->create(['email' => 'someone@example.com']);
    Socialite::fake('github', githubIdentity());

    $this->actingAs($user)->get('/auth/github/callback')->assertRedirect(route('security.edit'));

    expect(SocialAccount::query()->sole()->user_id)->toBe($user->id)->and(User::query()->count())->toBe(1);
});

it('refuses identities without an e-mail', function (): void {
    Socialite::fake('github', githubIdentity(['email' => null]));

    $this->get('/auth/github/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('returns to the login page when the provider fails', function (): void {
    Socialite::fake('github', fn () => throw new RuntimeException('access_denied'));

    $this->get('/auth/github/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');
});

it('lets passwordless accounts open security settings and set a first password', function (): void {
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)->get('/settings/security')->assertOk();
    $this->actingAs($user)
        ->put('/settings/password', ['password' => 'a-new-Password-123', 'password_confirmation' => 'a-new-Password-123'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->password)->not->toBeNull();
});
