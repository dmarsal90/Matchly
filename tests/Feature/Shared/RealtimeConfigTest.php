<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('sends the public Reverb address at runtime', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'public-key',
        'broadcasting.connections.reverb.secret' => 'never-sent',
        'broadcasting.connections.reverb.public' => ['host' => 'ws.example.com', 'port' => 443, 'scheme' => 'https'],
    ]);

    $this->get('/jobs')->assertInertia(fn (Assert $page) => $page
        ->where('reverb', ['key' => 'public-key', 'host' => 'ws.example.com', 'port' => 443, 'scheme' => 'https']));

    expect($this->get('/jobs')->getContent())->not->toContain('never-sent');
});

it('turns real time off when Reverb is not the broadcaster or has no public host', function (array $settings): void {
    config($settings);

    $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->where('reverb', null));
})->with([
    'log broadcaster' => [['broadcasting.default' => 'log']],
    'no public host' => [['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.public.host' => null]],
]);
