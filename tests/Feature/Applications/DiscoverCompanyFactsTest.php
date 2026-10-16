<?php

declare(strict_types=1);

use App\Modules\Applications\Infrastructure\Models\CompanyNote;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // DNS: the company site is public, "evil.example" resolves inside the network.
    $this->app->instance(PublicUrlGuard::class, new PublicUrlGuard(fn (string $host): array => $host === 'evil.example' ? ['10.0.0.5'] : ['93.184.216.34']));
});

function page(string $body, int $status = 200): Illuminate\Http\Client\Response|GuzzleHttp\Promise\PromiseInterface
{
    return Http::response($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
}

const HOME = '<html><head><meta name="description" content="We build open-source observability tools for small engineering teams."></head><body><main><p>Founded in 2019, Globex is a remote-first company with a team in twelve countries.</p></main></body></html>';

const ABOUT = '<html><body><main><p>Our public roadmap lists what we build next and everyone can vote on it.</p><p>Founded in 2019, Globex is a remote-first company with a team in twelve countries.</p></main></body></html>';

it('proposes what the company says about itself as notes waiting for approval', function (): void {
    Http::fake([
        'globex.example/robots.txt' => Http::response("User-agent: *\nDisallow: /careers\n", 200, ['Content-Type' => 'text/plain']),
        'globex.example/about' => page(ABOUT),
        'globex.example/*' => page(HOME),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('company-notes.discover'), ['company' => 'Globex', 'site_url' => 'https://globex.example'])->assertRedirect();

    $notes = CompanyNote::query()->orderBy('id')->get();
    expect($notes->pluck('fact')->all())->toBe([
        'We build open-source observability tools for small engineering teams.',
        'Founded in 2019, Globex is a remote-first company with a team in twelve countries.',
        'Our public roadmap lists what we build next and everyone can vote on it.',
    ])
        // Never used until the person approves them.
        ->and($notes->every(fn (CompanyNote $note): bool => $note->origin->value === 'web' && ! $note->approved))->toBeTrue()
        ->and($notes[1]->source_url)->toBe('https://globex.example/')
        ->and($notes[2]->source_url)->toBe('https://globex.example/about');

    // robots.txt forbids /careers: it is never requested.
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/careers'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->header('User-Agent')[0], 'Bot/'));
});

it('does not repeat facts it already has and keeps notes per person', function (): void {
    Http::fake(['globex.example/robots.txt' => Http::response('', 404), 'globex.example/*' => page(HOME)]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('company-notes.discover'), ['company' => 'Globex', 'site_url' => 'https://globex.example']);
    $first = CompanyNote::query()->count();
    $this->actingAs($user)->post(route('company-notes.discover'), ['company' => 'Globex', 'site_url' => 'https://globex.example']);

    expect($first)->toBe(2)->and(CompanyNote::query()->count())->toBe(2)
        ->and(CompanyNote::query()->where('user_id', '!=', $user->id)->count())->toBe(0);
});

it('never reads internal addresses, other hosts after a redirect, or non-text answers', function (): void {
    Http::fake([
        'evil.example/*' => page(HOME),
        'globex.example/robots.txt' => Http::response('', 404),
        'globex.example/about' => Http::response('', 302, ['Location' => 'https://social.example/globex']),
        'social.example/*' => page(ABOUT),
        'globex.example/*' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
    ]);
    $user = User::factory()->create();

    // The host resolves to a private address: refused before any request.
    $this->actingAs($user)->post(route('company-notes.discover'), ['company' => 'Evil', 'site_url' => 'https://evil.example']);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'evil.example'));

    $this->actingAs($user)->post(route('company-notes.discover'), ['company' => 'Globex', 'site_url' => 'https://globex.example']);
    expect(CompanyNote::query()->count())->toBe(0);
});

it('validates the site address and requires sign-in', function (): void {
    $this->post(route('company-notes.discover'), ['company' => 'Globex', 'site_url' => 'https://globex.example'])->assertRedirect(route('login'));

    foreach (['javascript:alert(1)', 'http://localhost/admin', 'ftp://globex.example', 'not a url'] as $bad) {
        $this->actingAs(User::factory()->create())->post(route('company-notes.discover'), ['company' => 'Globex', 'site_url' => $bad])->assertSessionHasErrors('site_url');
    }
});
