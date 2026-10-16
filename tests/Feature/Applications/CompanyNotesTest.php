<?php

declare(strict_types=1);

use App\Modules\Applications\Domain\Services\CompanyKey;
use App\Modules\Applications\Infrastructure\Models\CompanyNote;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('gives one key to every way of writing a company', function (): void {
    expect(CompanyKey::from('ACME, Inc.'))->toBe(CompanyKey::from('Acme Inc'))->toBe('acme')
        ->and(CompanyKey::from('Café Müller GmbH'))->toBe('cafe-muller')
        ->and(CompanyKey::from('Inc'))->toBe('inc');
});

it('keeps notes per user and company, encrypted, and shows them on every job of that company', function (): void {
    $user = User::factory()->create();
    $first = JobListing::factory()->create(['company' => 'Acme, Inc.']);
    $second = JobListing::factory()->create(['company' => 'ACME']);

    $this->actingAs($user)->post(route('company-notes.store'), ['company' => 'Acme, Inc.', 'fact' => 'They open-sourced their SDK in 2025', 'source_url' => 'https://acme.example/blog/sdk'])->assertRedirect();

    $note = CompanyNote::query()->sole();
    expect($note->approved)->toBeTrue()->and($note->origin->value)->toBe('manual')
        ->and(DB::table('company_notes')->value('fact'))->not->toContain('open-sourced');

    foreach ([$first, $second] as $job) {
        $this->actingAs($user)->get(route('jobs.show', ['job' => $job->id]))->assertInertia(fn (Assert $page) => $page
            ->has('companyNotes', 1)
            ->where('companyNotes.0.fact', 'They open-sourced their SDK in 2025'));
    }

    $this->actingAs(User::factory()->create())->get(route('jobs.show', ['job' => $first->id]))->assertInertia(fn (Assert $page) => $page->has('companyNotes', 0));
});

it('validates the note and its source link', function (array $payload, string $field): void {
    $this->actingAs(User::factory()->create())->post(route('company-notes.store'), ['company' => 'Acme', 'fact' => 'A valid fact here', ...$payload])->assertSessionHasErrors($field);
})->with([
    'too short' => [['fact' => 'hi'], 'fact'],
    'not a web link' => [['source_url' => 'javascript:alert(1)'], 'source_url'],
    'internal address' => [['source_url' => 'http://localhost/admin'], 'source_url'],
]);

it('lets only the owner delete a note', function (): void {
    $owner = User::factory()->create();
    $this->actingAs($owner)->post(route('company-notes.store'), ['company' => 'Acme', 'fact' => 'A valid fact here']);
    $note = CompanyNote::query()->sole();

    $this->actingAs(User::factory()->create())->delete(route('company-notes.destroy', $note))->assertForbidden();
    $this->actingAs($owner)->delete(route('company-notes.destroy', $note))->assertRedirect();

    expect(CompanyNote::query()->count())->toBe(0);
});

it('requires a signed-in user', function (): void {
    $this->post(route('company-notes.store'), ['company' => 'Acme', 'fact' => 'A valid fact here'])->assertRedirect(route('login'));
});
