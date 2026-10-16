<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\Queries\SkillMarket;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use Inertia\Testing\AssertableInertia as Assert;

function marketJob(array $tags, string $seenAgo = '5 days', ?int $pay = null): JobListing
{
    return JobListing::factory()->create([
        'tags' => $tags,
        'first_seen_at' => now()->sub($seenAgo),
        'salary_currency' => $pay === null ? null : 'EUR',
        'salary_annual_min' => $pay,
        'salary_annual_max' => $pay,
    ]);
}

function skilled(): User
{
    $user = User::factory()->create();
    $user->skills()->create(['source' => SkillSource::Declared, 'skill' => 'php', 'name' => 'PHP', 'level' => 4]);

    return $user;
}

it('counts open jobs per skill from their tags and leaves skills with no jobs out', function (): void {
    $user = skilled();
    $user->skills()->create(['source' => SkillSource::Declared, 'skill' => 'cobol', 'name' => 'COBOL', 'level' => 2]);
    foreach (range(1, 3) as $_) {
        marketJob(['php', 'laravel']);
    }
    marketJob(['go']);
    marketJob(['php'], '5 days')->forceFill(['status' => 'closed', 'closed_at' => now()])->save();

    $market = app(SkillMarket::class)->for($user->id);

    expect($market['total'])->toBe(4)
        ->and(array_column($market['skills'], 'skill'))->toBe(['php'])
        ->and($market['skills'][0])->toMatchArray(['jobs' => 3, 'share' => 75.0, 'own' => true]);
});

it('gives the pay only with enough listings that state it', function (): void {
    $user = skilled();
    foreach ([40, 50, 60, 70, 80] as $thousand) {
        marketJob(['php'], '5 days', $thousand * 1000);
    }
    $user->skills()->create(['source' => SkillSource::Declared, 'skill' => 'go', 'name' => 'Go', 'level' => 3]);
    foreach ([90, 95] as $thousand) {
        marketJob(['go'], '5 days', $thousand * 1000);
    }

    $skills = collect(app(SkillMarket::class)->for($user->id)['skills'])->keyBy('skill');

    expect($skills['php']['salary'])->toBe(['currency' => 'EUR', 'low' => 50000, 'median' => 60000, 'high' => 70000, 'sample' => 5])
        ->and($skills['go']['salary'])->toBeNull();
});

it('reads a trend only against the whole market and with enough new listings', function (): void {
    $user = skilled();
    // Last 30 days: 12 php of 12. Before that: 2 php of 12. PHP gains share.
    foreach (range(1, 12) as $_) {
        marketJob(['php'], '10 days');
    }
    foreach (range(1, 2) as $_) {
        marketJob(['php'], '45 days');
    }
    foreach (range(1, 10) as $_) {
        marketJob(['other'], '45 days');
    }

    expect(app(SkillMarket::class)->for($user->id)['skills'][0]['trend'])->toBe('up');

    $quiet = skilled();
    JobListing::query()->delete();
    marketJob(['php'], '10 days');
    expect(app(SkillMarket::class)->for($quiet->id)['skills'][0]['trend'])->toBeNull();
});

it('shows the page to signed-in people only', function (): void {
    $this->get('/market')->assertRedirect(route('login'));

    marketJob(['php']);
    $this->actingAs(skilled())->get('/market')->assertInertia(fn (Assert $page) => $page
        ->component('market/Index')
        ->where('minSalarySample', 5)
        ->where('skills.0.skill', 'php'));
});
