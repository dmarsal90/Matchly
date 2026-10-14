<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shares only safe user fields with every page', function (): void {
    $user = User::factory()->create(['is_admin' => true]);

    $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.email', $user->email)
        ->where('auth.user.has_password', true)
        ->where('auth.user.is_admin', true)
        ->missing('auth.user.password')
        ->missing('auth.user.two_factor_secret')
        ->missing('auth.user.remember_token'));
});

it('shares the OAuth providers that are configured', function (): void {
    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('oauthProviders', ['google']));
});

it('remembers the language choice on the account', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/profile')->put('/locale', ['locale' => 'pt']);

    expect($user->refresh()->locale)->toBe('pt');
    $this->actingAs($user)->withHeader('Accept-Language', 'en')->get('/profile')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'pt'));
});

it('renders the legal pages publicly', function (string $path, string $component): void {
    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([['/privacy', 'legal/Privacy'], ['/terms', 'legal/Terms']]);

it('passes retention figures to the privacy policy', function (): void {
    $this->get('/privacy')->assertInertia(fn (Assert $page) => $page->where('retention.days', '30'));
});
