<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config(['app.debug' => false]);
    Route::get('/_boom', fn () => throw new RuntimeException('secret detail'));
    Route::get('/_forbidden', fn () => abort(403));
    Route::get('/_maintenance', fn () => abort(503));
});

it('shows a friendly, branded 404 in the language of the browser', function (): void {
    $this->get('/definitely-not-here', ['Accept-Language' => 'pt'])
        ->assertNotFound()
        ->assertSee('Não encontrámos esta página')
        ->assertSee(config('app.name'))
        ->assertDontSee('Illuminate');

    $this->get('/definitely-not-here', ['Accept-Language' => 'en'])->assertSee('We could not find this page');
});

it('shows the 403 page and uses the signed-in person\'s language', function (): void {
    $this->actingAs(User::factory()->create(['locale' => 'es']))->get('/_forbidden')
        ->assertForbidden()
        ->assertSee('No tienes acceso a esta página');
});

it('shows the 500 page without leaking the error', function (): void {
    $this->get('/_boom', ['Accept-Language' => 'es'])
        ->assertStatus(500)
        ->assertSee('Algo ha fallado de nuestro lado')
        ->assertSee('Reintentar')
        ->assertDontSee('secret detail')
        ->assertDontSee('RuntimeException');
});

it('shows the 503 page with a retry link', function (): void {
    $this->get('/_maintenance', ['Accept-Language' => 'en'])->assertStatus(503)->assertSee('We will be right back')->assertSee('Try again');
});

it('keeps JSON answers for the API', function (): void {
    $this->getJson('/api/extension/packages')->assertUnauthorized()->assertJsonStructure(['message']);
    $this->getJson('/nope-json')->assertNotFound()->assertJsonStructure(['message']);
});

it('keeps non-admins out of the admin panel with a 403 or a redirect', function (): void {
    $response = $this->actingAs(User::factory()->create())->get('/admin');

    expect($response->status())->toBeIn([302, 403]);
});
