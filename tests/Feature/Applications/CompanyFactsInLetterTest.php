<?php

declare(strict_types=1);

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Domain\Enums\NoteOrigin;
use App\Modules\Applications\Domain\Services\CompanyKey;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Applications\Infrastructure\Models\CompanyNote;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function noteAbout(User $user, string $company, string $fact, bool $approved = true): void
{
    $note = new CompanyNote(['company_key' => CompanyKey::from($company), 'company' => $company, 'fact' => $fact, 'origin' => NoteOrigin::Manual, 'approved' => $approved]);
    $note->user()->associate($user);
    $note->save();
}

function quickApplyTo(User $user, JobListing $job, array $payload = []): ApplicationPackage
{
    test()->actingAs($user)->post(route('jobs.quick-apply', $job), ['tone' => 'formal', 'length' => 'medium', ...$payload])->assertRedirect()->assertSessionHasNoErrors();

    return ApplicationPackage::query()->latest('id')->firstOrFail();
}

it('quotes the approved notes about the company in the template letter', function (): void {
    $user = candidateWithProfile();
    $job = spanishLaravelJob();
    noteAbout($user, 'Globex', 'Lanzaron un SDK de código abierto en 2025.');
    noteAbout($user, 'GLOBEX Inc', 'Publican su hoja de ruta');
    noteAbout($user, 'Globex', 'Una nota sin aprobar', approved: false);
    noteAbout($user, 'Initech', 'Nota de otra empresa');
    noteAbout(User::factory()->create(), 'Globex', 'Nota de otra persona');

    $letter = quickApplyTo($user, $job)->cover_letter;

    expect($letter)->toContain('De Globex me atrae especialmente lo siguiente: Lanzaron un SDK de código abierto en 2025.')
        ->toContain('lo siguiente: Publican su hoja de ruta.')
        ->not->toContain('sin aprobar')->not->toContain('otra empresa')->not->toContain('otra persona');
});

it('writes the same letter as before when there are no notes', function (): void {
    $letter = quickApplyTo(candidateWithProfile(), spanishLaravelJob())->cover_letter;

    expect($letter)->not->toContain('me atrae especialmente');
});

it('hands only the approved notes to the AI', function (): void {
    config(['ai.enabled' => true, 'ai.fallback_order' => ['anthropic'], 'ai.providers.anthropic.api_key' => 'server-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'submit_result', 'input' => ['greeting' => 'Hola:', 'body' => str_repeat('Texto de la carta de presentación. ', 5), 'closing' => 'Un saludo,']]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]])]);

    $user = candidateWithProfile();
    noteAbout($user, 'Globex', 'Lanzaron un SDK de código abierto');
    noteAbout($user, 'Globex', 'Secreto sin aprobar', approved: false);

    quickApplyTo($user, spanishLaravelJob(), ['use_ai' => true, 'ai_consent' => '1']);

    Http::assertSent(function (Request $request): bool {
        $body = (string) json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return str_contains($body, 'company_facts') && str_contains($body, 'Lanzaron un SDK de código abierto') && ! str_contains($body, 'Secreto sin aprobar');
    });
});

it('turns the notes into questions to ask the company in the interview pack', function (): void {
    $user = candidateWithProfile();
    $job = spanishLaravelJob();
    noteAbout($user, 'Globex', 'publican su hoja de ruta');

    $application = new Application;
    $application->forceFill(['user_id' => $user->id, 'job_listing_id' => $job->id, 'status' => ApplicationStatus::Applied, 'status_changed_at' => now(), 'applied_at' => now()])->save();
    $this->actingAs($user)->post(route('applications.interview-prep.store', $application), []);

    $ask = $application->interviewPrep()->firstOrFail()->content['ask'];

    expect($ask[0])->toBe('He visto que publican su hoja de ruta. ¿Cómo influye eso en el día a día del equipo?')->and($ask)->toHaveCount(4);
});
