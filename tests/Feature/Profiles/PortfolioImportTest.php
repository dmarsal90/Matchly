<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Application\Actions\RevokeConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Actions\ReadPortfolio;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use App\Modules\Profiles\Infrastructure\Queue\ImportPortfolioJob;
use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->app->instance(PublicUrlGuard::class, new PublicUrlGuard(fn (string $host): array => ['93.184.216.34']));
});

/**
 * @param  array<string, string>  $pages  path => html
 */
function fakePortfolio(array $pages, string $robots = ''): void
{
    Http::fake(function (Request $request) use ($pages, $robots) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/robots.txt') {
            return $robots === '' ? Http::response('', 404) : Http::response($robots, 200, ['Content-Type' => 'text/plain']);
        }

        return isset($pages[$path]) ? Http::response($pages[$path], 200, ['Content-Type' => 'text/html']) : Http::response('', 404);
    });
}

function startedPortfolio(User $user, string $url = 'https://ana.example/'): ProfileImport
{
    Queue::fake();
    test()->actingAs($user)->post('/profile/imports/portfolio', ['url' => $url, 'consent' => '1'])->assertRedirect()->assertSessionHasNoErrors();
    Queue::assertPushedOn('high', ImportPortfolioJob::class);

    return ProfileImport::query()->where('user_id', $user->id)->sole();
}

it('requires consent and a public https address', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/imports/portfolio', ['url' => 'https://ana.example'])->assertSessionHasErrors('consent');
    $this->actingAs($user)->post('/profile/imports/portfolio', ['url' => 'http://ana.example', 'consent' => '1'])->assertSessionHasErrors('url');
    $this->actingAs($user)->post('/profile/imports/portfolio', ['url' => 'https://localhost/', 'consent' => '1'])->assertSessionHasErrors('url');
    $this->actingAs($user)->post('/profile/imports/portfolio', ['url' => 'https://10.0.0.5/', 'consent' => '1'])->assertSessionHasErrors('url');

    expect(ProfileImport::query()->count())->toBe(0);
});

it('records consent and queues the read', function (): void {
    $user = User::factory()->create();

    $import = startedPortfolio($user);

    expect($import->status)->toBe(ImportStatus::Processing)
        ->and($import->source)->toBe(SkillSource::Portfolio)
        ->and($import->reference)->toBe('https://ana.example/')
        ->and($user->hasConsented(ConsentPurpose::PortfolioImport))->toBeTrue();
});

it('lists the technologies the site mentions, with no invented levels or years', function (): void {
    fakePortfolio([
        '/' => '<html><body><script>var Rust = 1;</script><p>I build APIs with Laravel and PHP. Laravel is my daily tool.</p></body></html>',
        '/projects' => '<html><body><h2>Dashboard</h2><p>Vue.js and PostgreSQL, deployed with Docker.</p></body></html>',
    ]);
    $import = startedPortfolio(User::factory()->create());

    app(ReadPortfolio::class)->handle($import);

    $import->refresh();
    $skills = collect($import->payload['skills'])->keyBy('skill');
    expect($import->status)->toBe(ImportStatus::PendingReview)
        ->and($skills->keys()->all())->toContain('laravel', 'php', 'vue', 'postgresql', 'docker')
        ->and($skills->keys()->all())->not->toContain('rust')
        ->and($skills['laravel']['evidence']['mentions'])->toBe(2)
        ->and($skills['laravel']['level'])->toBeNull()->and($skills['laravel']['years'])->toBeNull()
        ->and($import->payload['pages'])->toBe(['https://ana.example/', 'https://ana.example/projects']);
});

it('obeys robots.txt', function (): void {
    fakePortfolio([
        '/' => '<p>Laravel</p>',
        '/projects' => '<p>Docker</p>',
    ], "User-agent: *\nDisallow: /projects\n");
    $import = startedPortfolio(User::factory()->create());

    app(ReadPortfolio::class)->handle($import);

    expect(collect($import->refresh()->payload['skills'])->pluck('skill')->all())->toBe(['laravel']);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/projects'));
});

it('does not take a redirect to another host for their site', function (): void {
    Http::fake(['*' => Http::response('<p>Laravel</p>', 302, ['Location' => 'https://elsewhere.example/'])]);
    $other = startedPortfolio(User::factory()->create());
    app(ReadPortfolio::class)->handle($other);
    expect($other->refresh()->status)->toBe(ImportStatus::Failed)->and($other->error)->toBe('unavailable');
});

it('fails with a clear reason when the page names no known technology or is unreachable', function (array $pages, string $reason): void {
    fakePortfolio($pages);
    $import = startedPortfolio(User::factory()->create());

    app(ReadPortfolio::class)->handle($import);

    expect($import->refresh()->status)->toBe(ImportStatus::Failed)->and($import->error)->toBe($reason);
})->with([
    'no technology' => [['/' => '<p>Hello and welcome to my garden blog about tomatoes.</p>'], 'nothing_found'],
    'unreachable' => [[], 'unavailable'],
]);

it('drops the import when consent was withdrawn while it waited', function (): void {
    fakePortfolio(['/' => '<p>Laravel</p>']);
    $user = User::factory()->create();
    $import = startedPortfolio($user);
    app(RevokeConsent::class)->handle($user, ConsentPurpose::PortfolioImport);

    // The worker still holds the stale record: it must not read anything.
    app(ReadPortfolio::class)->handle($import);

    expect(ProfileImport::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('saves only the skills the person keeps, and remembers the address on their profile', function (): void {
    fakePortfolio(['/' => '<p>Laravel, PHP and Docker.</p>']);
    $user = User::factory()->create();
    $import = startedPortfolio($user);
    app(ReadPortfolio::class)->handle($import);

    $this->actingAs($user)->get(route('skills.imports.show', $import))->assertOk();
    $this->post(route('skills.imports.confirm', $import), ['skills' => ['laravel']])->assertRedirect(route('skills.index'));

    expect($user->skills()->where('source', SkillSource::Portfolio)->pluck('skill')->all())->toBe(['laravel'])
        ->and($user->profile()->first()->portfolio_url)->toBe('https://ana.example/');
});

it('deletes what came from the portfolio when its consent is withdrawn', function (): void {
    $user = User::factory()->create();
    app(GrantConsent::class)->handle($user, ConsentPurpose::PortfolioImport);
    $user->profileImports()->create(['source' => SkillSource::Portfolio, 'status' => ImportStatus::Confirmed, 'payload' => ['skills' => []]]);
    $user->skills()->create(['source' => SkillSource::Portfolio, 'skill' => 'php', 'name' => 'PHP']);

    app(RevokeConsent::class)->handle($user, ConsentPurpose::PortfolioImport);

    expect(ProfileImport::query()->count())->toBe(0)->and($user->skills()->count())->toBe(0);
});
