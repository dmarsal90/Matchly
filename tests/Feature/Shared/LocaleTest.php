<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('shares the configured product name and locales with every page', function (): void {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->where('name', config('app.name'))
        ->where('supportedLocales', ['es', 'en', 'pt'])
    );
});

it('negotiates the locale from the Accept-Language header', function (): void {
    $this->withHeader('Accept-Language', 'pt-BR,pt;q=0.9,en;q=0.8')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'pt'));
});

it('falls back to the default locale for unsupported languages', function (): void {
    $this->withHeader('Accept-Language', 'de-DE')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('locale', config('app.locale')));
});

it('lets visitors switch the locale and remembers it in the session', function (): void {
    $this->from('/')->put('/locale', ['locale' => 'en'])->assertRedirect('/');

    $this->withHeader('Accept-Language', 'pt')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

it('rejects unsupported locales', function (): void {
    $this->from('/')->put('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
});

it('renders the page title and metadata from configuration', function (): void {
    $this->get('/')
        ->assertSee('<meta name="application-name" content="'.e(config('app.name')).'">', false);
});
