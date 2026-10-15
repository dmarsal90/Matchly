<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\RevokeConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Domain\Enums\OAuthProvider;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Actions\ReadGithubProfile;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use App\Modules\Profiles\Infrastructure\Queue\ImportGithubJob;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-14 12:00:00');
    config(['github.token' => null, 'github.api_url' => 'https://api.github.com']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Fakes the public GitHub API for user "octocat": a Laravel + Vue app, a Go
 * service, an old repository and a fork (the last two must be ignored).
 *
 * @param  array<string, Closure(Request): mixed>  $overrides  path => response
 */
function fakeGithub(array $overrides = []): void
{
    $repo = fn (string $name, string $pushed, array $extra = []): array => [
        'name' => $name, 'html_url' => "https://github.com/octocat/{$name}", 'description' => "{$name} description",
        'fork' => false, 'archived' => false, 'owner' => ['login' => 'octocat'], 'stargazers_count' => 3,
        'created_at' => '2022-01-10T00:00:00Z', 'pushed_at' => $pushed, 'topics' => [], ...$extra,
    ];

    $routes = [
        '/users/octocat' => fn () => Http::response(['login' => 'octocat', 'type' => 'User', 'name' => 'The Octocat', 'html_url' => 'https://github.com/octocat', 'public_repos' => 4], 200, ['ETag' => '"user-v1"']),
        '/users/octocat/repos' => fn () => Http::response([
            $repo('shop', '2026-09-01T00:00:00Z', ['topics' => ['laravel', 'hacktoberfest']]),
            $repo('api', '2025-03-01T00:00:00Z', ['created_at' => '2024-01-01T00:00:00Z']),
            $repo('forked', '2026-09-01T00:00:00Z', ['fork' => true]),
            $repo('ancient', '2019-01-01T00:00:00Z'),
        ]),
        '/repos/octocat/shop/languages' => fn () => Http::response(['PHP' => 60000, 'Vue' => 30000, 'Blade' => 8000, 'Makefile' => 100]),
        '/repos/octocat/shop/contents/' => fn () => Http::response([
            ['name' => 'composer.json', 'type' => 'file', 'size' => 300],
            ['name' => 'package.json', 'type' => 'file', 'size' => 200],
            ['name' => 'Dockerfile', 'type' => 'file', 'size' => 50],
            ['name' => 'app', 'type' => 'dir', 'size' => 0],
        ]),
        '/repos/octocat/shop/contents/composer.json' => fn () => Http::response('{"require":{"laravel/framework":"^13.0"},"require-dev":{"pestphp/pest":"^5"}}'),
        '/repos/octocat/shop/contents/package.json' => fn () => Http::response('{"dependencies":{"vue":"^3.5","@inertiajs/vue3":"^3"}}'),
        '/repos/octocat/shop/readme' => fn () => Http::response("# Shop\n\n![badge](https://img.shields.io/x.svg) An online **shop** built with [Laravel](https://laravel.com)."),
        '/repos/octocat/api/languages' => fn () => Http::response(['Go' => 50000]),
        '/repos/octocat/api/contents/' => fn () => Http::response([['name' => 'go.mod', 'type' => 'file', 'size' => 100]]),
        '/repos/octocat/api/contents/go.mod' => fn () => Http::response("module api\n\nrequire github.com/lib/pq v1.10.9\n"),
        '/repos/octocat/api/readme' => fn () => Http::response(['message' => 'Not Found'], 404),
        ...$overrides,
    ];

    Http::fake(function (Request $request) use ($routes) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $handler = $routes[$path] ?? null;

        return $handler !== null ? $handler($request) : Http::response(['message' => 'Not Found'], 404);
    });
}

it('requires consent and a valid username', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'octocat'])->assertSessionHasErrors('consent');
    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'not a user!', 'consent' => '1'])->assertSessionHasErrors('username');

    expect(ProfileImport::query()->count())->toBe(0);
});

it('records consent and queues the import', function (): void {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'https://github.com/octocat', 'consent' => '1'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $import = ProfileImport::query()->sole();
    expect($import->status)->toBe(ImportStatus::Processing)
        ->and($import->source)->toBe(SkillSource::Github)
        ->and($import->reference)->toBe('octocat')
        ->and($user->hasConsented(ConsentPurpose::GithubImport))->toBeTrue();
    Queue::assertPushedOn('high', ImportGithubJob::class);
});

it('reads public repositories into skill evidence for review', function (): void {
    fakeGithub();
    config(['github.token' => 'server-token']);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'octocat', 'consent' => '1'])->assertSessionHasNoErrors();

    $import = ProfileImport::query()->sole();
    $payload = $import->payload;
    $skills = collect($payload['skills'])->keyBy('skill');

    expect($import->status)->toBe(ImportStatus::PendingReview)
        ->and($payload['username'])->toBe('octocat')
        ->and($payload['authenticated'])->toBeTrue()
        // The fork and the repository not pushed to in three years are ignored.
        ->and(array_column($payload['repositories'], 'name'))->toBe(['shop', 'api'])
        ->and($skills->keys()->sort()->values()->all())->toBe(['docker', 'go', 'inertia', 'laravel', 'pest', 'php', 'postgresql', 'vue'])
        ->and($skills['laravel']['evidence']['signals'])->toBe(['topic', 'dependency'])
        ->and($skills['laravel'])->toMatchArray(['level' => 2, 'last_used_year' => 2026, 'years' => 4.5])
        ->and($skills['go'])->toMatchArray(['last_used_year' => 2025, 'years' => 1.0])
        ->and($skills['vue']['evidence']['repositories'])->toBe(['shop'])
        ->and($payload['repositories'][0]['readme_excerpt'])->toBe("# Shop\n\n An online **shop** built with Laravel.")
        ->and($payload['repositories'][1]['readme_excerpt'])->toBeNull();

    // Only public, read-only endpoints were called, with the server token.
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://api.github.com/')
        && $request->hasHeader('Authorization', 'Bearer server-token')
        && $request->hasHeader('X-GitHub-Api-Version', '2022-11-28'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/forked/') || str_contains($request->url(), '/ancient/'));
});

it('reads fewer repositories and no READMEs without a token', function (): void {
    fakeGithub();
    config(['github.anonymous_max_repositories' => 1]);

    $this->actingAs(User::factory()->create())->post('/profile/imports/github', ['username' => 'octocat', 'consent' => '1']);

    $payload = ProfileImport::query()->sole()->payload;
    expect($payload['authenticated'])->toBeFalse()
        ->and(array_column($payload['repositories'], 'name'))->toBe(['shop']);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/readme') || $request->hasHeader('Authorization'));
});

it('uses the linked GitHub account token for its own login only', function (): void {
    fakeGithub();
    $user = User::factory()->create();
    $user->socialAccounts()->create(['provider' => OAuthProvider::Github, 'provider_user_id' => '1', 'nickname' => 'OctoCat', 'token' => 'user-token']);

    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'octocat', 'consent' => '1']);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer user-token'));

    // Someone else's login is read without the user's token.
    Http::fake();
    fakeGithub(['/users/hubot' => fn () => Http::response(['message' => 'Not Found'], 404)]);
    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'hubot', 'consent' => '1']);
    Http::assertNotSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer user-token'));
});

it('revalidates cached responses with ETags', function (): void {
    $user = User::factory()->create();
    fakeGithub();
    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'octocat', 'consent' => '1']);

    fakeGithub(['/users/octocat' => fn (Request $request) => $request->hasHeader('If-None-Match', '"user-v1"')
        ? Http::response('', 304)
        : Http::response(['message' => 'should have been conditional'], 500)]);
    ProfileImport::query()->delete();
    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'octocat', 'consent' => '1']);

    expect(ProfileImport::query()->sole()->payload['name'])->toBe('The Octocat');
});

it('reports failures with a reason the user can understand', function (array $overrides, string $reason): void {
    fakeGithub($overrides);

    $this->actingAs(User::factory()->create())->post('/profile/imports/github', ['username' => 'octocat', 'consent' => '1']);

    $import = ProfileImport::query()->sole();
    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->error)->toBe($reason)
        ->and($import->payload)->toBeNull();
})->with([
    'unknown user' => [['/users/octocat' => fn () => Http::response(['message' => 'Not Found'], 404)], 'not_found'],
    'organisation' => [['/users/octocat' => fn () => Http::response(['login' => 'octocat', 'type' => 'Organization'])], 'not_found'],
    'rate limited' => [['/users/octocat' => fn () => Http::response(['message' => 'API rate limit exceeded'], 403, ['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => '1760000000'])], 'rate_limited'],
    'no recent repositories' => [['/users/octocat/repos' => fn () => Http::response([])], 'no_repositories'],
    'server error' => [['/users/octocat/repos' => fn () => Http::response('oops', 502)], 'unavailable'],
]);

it('lets only one import per source run at a time', function (): void {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'octocat', 'consent' => '1'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'octocat', 'consent' => '1'])->assertSessionHasErrors('import');

    expect(ProfileImport::query()->count())->toBe(1);
});

it('drops a queued import whose consent was withdrawn meanwhile', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'octocat', 'consent' => '1']);

    app(RevokeConsent::class)->handle($user, ConsentPurpose::GithubImport);
    Http::fake();
    $import = ProfileImport::query()->first();

    // Revoking already deletes it; the job must cope with either outcome.
    expect($import)->toBeNull();
    $orphan = $user->profileImports()->create(['source' => SkillSource::Github, 'status' => ImportStatus::Processing, 'reference' => 'octocat']);
    app(ReadGithubProfile::class)->handle($orphan);

    expect(ProfileImport::query()->count())->toBe(0);
    Http::assertNothingSent();
});
