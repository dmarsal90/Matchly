<?php

declare(strict_types=1);

it('sends baseline security headers on every response', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
});

it('sends a nonce-based content security policy when enabled', function (): void {
    config(['security.csp.enabled' => true, 'security.csp.report_only' => false]);

    $response = $this->get('/');

    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/");
});

it('omits the content security policy when disabled', function (): void {
    config(['security.csp.enabled' => false]);

    $this->get('/')->assertHeaderMissing('Content-Security-Policy');
});

it('exposes a health endpoint for load balancers', function (): void {
    $this->get('/up')->assertOk();
});
