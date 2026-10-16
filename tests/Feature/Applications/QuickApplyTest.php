<?php

declare(strict_types=1);

use App\Modules\Applications\Application\Generators\DraftReviewer;
use App\Modules\Applications\Domain\Contracts\ApplicationAssistant;
use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Domain\Enums\LetterLength;
use App\Modules\Applications\Domain\Enums\PackageStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\Actions\ResolveJobRequirements;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/** What the draft reviewer adds is covered on its own; these tests look at the rest. */
function withoutReview(array $warnings): array
{
    return array_values(array_filter($warnings, fn (array $warning): bool => ! in_array($warning['key'], DraftReviewer::KEYS, true)));
}

function prepare(User $user, JobListing $job, array $payload = []): ApplicationPackage
{
    test()->actingAs($user)->post(route('jobs.quick-apply', $job), ['tone' => 'formal', 'length' => 'medium', ...$payload])->assertRedirect()->assertSessionHasNoErrors();

    return ApplicationPackage::query()->latest('id')->firstOrFail();
}

it('requires a verified account and valid options', function (): void {
    $job = spanishLaravelJob();
    $this->post(route('jobs.quick-apply', $job), ['tone' => 'formal', 'length' => 'short'])->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->actingAs($user)->post(route('jobs.quick-apply', $job), ['tone' => 'rude', 'length' => 'short'])->assertSessionHasErrors('tone');
    $this->actingAs($user)->post(route('jobs.quick-apply', $job), ['tone' => 'formal', 'length' => 'short', 'use_ai' => true])->assertSessionHasErrors('ai_consent');
});

it('prepares a package without AI: tailored CV and a template letter in the posting language', function (): void {
    $user = candidateWithProfile();
    $job = spanishLaravelJob();

    $response = $this->actingAs($user)->post(route('jobs.quick-apply', $job), ['tone' => 'formal', 'length' => 'medium']);
    $package = ApplicationPackage::query()->sole();
    $response->assertRedirect(route('applications.packages.show', $package));

    $resume = $package->resumeData();
    expect($package->status)->toBe(PackageStatus::Draft)
        ->and($package->language)->toBe('es')
        ->and($package->resume_source->value)->toBe('rules')
        ->and($package->letter_source->value)->toBe('rules')
        ->and($resume->contact)->toMatchArray(['name' => 'Ana García', 'phone' => '+34 600 000 000', 'location' => 'Valencia, España'])
        ->and($resume->skills)->toBe(['Laravel', 'PostgreSQL', 'Figma', 'PHP'])
        ->and($resume->experience[0]['bullets'][0])->toBe('Built REST APIs with Laravel and PostgreSQL.')
        ->and($resume->keywords)->toBe(['Laravel', 'PostgreSQL'])
        ->and($package->master_resume['skills'])->toBe(['Figma', 'Laravel', 'PHP', 'PostgreSQL'])
        ->and($package->cover_letter)->toStartWith('Estimado equipo de selección:')
        ->and($package->cover_letter)->toContain('Desarrollador Backend Senior en Globex')
        ->and($package->cover_letter)->toContain('Actualmente trabajo como Senior Backend Engineer en Acme. Cuento con 6 años')
        ->and($package->cover_letter)->toContain('Laravel y PostgreSQL')
        // Kubernetes is asked for but not in the profile: never claimed.
        ->and($package->cover_letter)->not->toContain('Kubernetes')
        ->and($package->cover_letter)->toEndWith("Atentamente,\nAna García")
        ->and(withoutReview($package->warnings))->toBe([])
        ->and(Application::query()->sole()->status)->toBe(ApplicationStatus::Prepared);

    $this->get(route('applications.packages.show', $package))->assertInertia(fn (Assert $page) => $page
        ->component('applications/Package')
        ->where('package.status', 'draft')
        ->where('job.company', 'Globex')
        ->has('package.resume.experience', 1)
        ->has('package.master_resume'));
});

it('keeps later tracking statuses when preparing again', function (): void {
    $user = candidateWithProfile();
    $job = spanishLaravelJob();
    Application::query()->forceCreate(['user_id' => $user->id, 'job_listing_id' => $job->id, 'status' => ApplicationStatus::Interview, 'status_changed_at' => now()]);

    prepare($user, $job);
    prepare($user, $job);

    expect(Application::query()->sole()->status)->toBe(ApplicationStatus::Interview)
        ->and(ApplicationPackage::query()->count())->toBe(2);
});

it('warns when the profile is too thin for a good CV', function (): void {
    $package = prepare(User::factory()->create(), spanishLaravelJob());

    expect(array_column(withoutReview($package->warnings), 'key'))->toBe(['empty_profile']);
});

it('refuses jobs held for moderation or closed', function (): void {
    $user = candidateWithProfile();

    $this->actingAs($user)->post(route('jobs.quick-apply', spanishLaravelJob(['status' => JobStatus::Flagged])), ['tone' => 'formal', 'length' => 'short'])->assertNotFound();
    $this->actingAs($user)->post(route('jobs.quick-apply', spanishLaravelJob(['status' => JobStatus::Closed])), ['tone' => 'formal', 'length' => 'short'])->assertStatus(410);
});

describe('with AI', function (): void {
    beforeEach(function (): void {
        config(['ai.enabled' => true, 'ai.fallback_order' => ['anthropic'], 'ai.providers.anthropic.api_key' => 'server-key']);

        Http::fake(['api.anthropic.com/*' => function (Request $request) {
            $isLetter = str_contains((string) json_encode($request->data()['tools'][0]['input_schema']), 'greeting');
            $input = $isLetter
                ? [
                    'greeting' => 'Estimado equipo de Globex:',
                    'body' => "Tengo 10 años de experiencia construyendo APIs REST con Laravel y PostgreSQL en Acme.\n\nTambién domino Kubernetes y Golang.",
                    'closing' => 'Un saludo,',
                ]
                : [
                    'summary' => 'Ingeniera backend que lideró equipos de 50 personas en Google.',
                    'skills' => ['Laravel', 'PostgreSQL', 'Kubernetes', 'PHP'],
                    'experience' => [
                        ['index' => 0, 'bullets' => ['Designed and built REST APIs with Laravel and PostgreSQL.', 'Increased revenue by 300% across five markets worldwide.', 'Mentored two junior developers.']],
                        ['index' => 7, 'bullets' => ['Invented position.']],
                    ],
                    'keywords' => ['Laravel', 'Kubernetes'],
                ];

            return Http::response(['content' => [['type' => 'tool_use', 'name' => 'submit_result', 'input' => $input]], 'usage' => ['input_tokens' => 900, 'output_tokens' => 300]]);
        }]);
    });

    it('keeps only faithful rewording and flags unbacked claims in the letter', function (): void {
        $user = candidateWithProfile();
        $package = prepare($user, spanishLaravelJob(), ['use_ai' => true, 'ai_consent' => '1']);
        $resume = $package->resumeData();

        expect($user->hasConsented(ConsentPurpose::AiProcessing))->toBeTrue()
            ->and($package->resume_source->value)->toBe('ai')
            // Skill the CV lacks is dropped; the rest follow in rule order.
            ->and($resume->skills)->toBe(['Laravel', 'PostgreSQL', 'PHP', 'Figma'])
            // Reworded bullet kept, invented achievement dropped, invented position ignored.
            ->and($resume->experience)->toHaveCount(1)
            ->and($resume->experience[0]['bullets'])->toBe(['Designed and built REST APIs with Laravel and PostgreSQL.', 'Mentored two junior developers.'])
            // Summary with new facts rejected in favour of the person's own.
            ->and($resume->summary)->toBe('Backend developer focused on APIs.')
            ->and($resume->keywords)->toBe(['Laravel'])
            ->and($package->letter_source->value)->toBe('ai')
            ->and($package->cover_letter)->toEndWith("Un saludo,\nAna García")
            // jsonb does not keep key order (PostgreSQL): compare key/value pairs.
            ->and(withoutReview($package->warnings))->toEqual([
                ['key' => 'unbacked_skills', 'params' => ['skills' => 'Go, Kubernetes']],
                ['key' => 'years_claim', 'params' => ['claimed' => '10', 'have' => '6']],
            ]);

        Http::assertSent(fn (Request $request): bool => $request->data()['model'] === config('ai.providers.anthropic.models.quality'));
    });

});

it('falls back to rules and the template when the AI fails', function (): void {
    config(['ai.enabled' => true, 'ai.fallback_order' => ['anthropic'], 'ai.providers.anthropic.api_key' => 'server-key']);
    Http::fake(['api.anthropic.com/*' => Http::response('down', 500)]);
    $user = candidateWithProfile();
    $user->consents()->create(['purpose' => ConsentPurpose::AiProcessing, 'policy_version' => config('privacy.policy_version'), 'granted_at' => now()]);

    $package = prepare($user, spanishLaravelJob(), ['use_ai' => true]);

    expect($package->letter_source->value)->toBe('rules')
        ->and(withoutReview($package->warnings))->toBe([['key' => 'ai_unavailable', 'params' => ['reason' => 'failed']]]);
});

it('saves edits, recomputes warnings and sends approved packages back to draft', function (): void {
    $user = candidateWithProfile();
    $package = prepare($user, spanishLaravelJob());
    $this->post(route('applications.packages.approve', $package))->assertSessionHasNoErrors();
    expect($package->fresh()->status)->toBe(PackageStatus::Approved);

    $this->put(route('applications.packages.update', $package), [
        'cover_letter' => "Hola:\n\nSé Rust desde hace 12 años.\n\nAna",
        'resume' => ['summary' => 'Ingeniera backend.', 'skills' => ['PHP', 'Laravel'], 'experience' => [['title' => 'Senior Backend Engineer', 'company' => 'Acme', 'started_on' => '2021-03', 'bullets' => ['Built REST APIs.']]]],
    ])->assertSessionHasNoErrors();

    $package->refresh();
    expect($package->status)->toBe(PackageStatus::Draft)
        ->and($package->approved_at)->toBeNull()
        ->and($package->letter_source->value)->toBe('user')
        ->and($package->resume_source->value)->toBe('user')
        ->and($package->resumeData()->skills)->toBe(['PHP', 'Laravel'])
        ->and($package->resumeData()->contact['name'])->toBe('Ana García')
        ->and(array_column(withoutReview($package->warnings), 'key'))->toBe(['unbacked_skills', 'years_claim']);
});

it('exports the CV as a PDF only once approved', function (): void {
    $user = candidateWithProfile();
    $package = prepare($user, spanishLaravelJob());

    $this->get(route('applications.packages.resume', $package))->assertForbidden();

    $this->post(route('applications.packages.approve', $package));
    $response = $this->get(route('applications.packages.resume', $package))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF-')
        ->and($response->headers->get('Content-Disposition'))->toContain('ana-garcia-globex.pdf');
});

it('offers several CV looks that keep the same one-column text, and falls back to the classic one', function (): void {
    $package = prepare(candidateWithProfile(), spanishLaravelJob());
    $this->post(route('applications.packages.approve', $package));
    $html = fn (App\Modules\Applications\Domain\Enums\ResumeTemplate $template): string => view('pdf.resume', ['resume' => $package->refresh()->resumeData(), 'locale' => 'es', 'template' => $template])->render();

    expect($html(App\Modules\Applications\Domain\Enums\ResumeTemplate::Modern))->toContain('#1e3a8a')
        ->and($html(App\Modules\Applications\Domain\Enums\ResumeTemplate::Compact))->toContain('font-size: 9pt; line-height')
        ->and($html(App\Modules\Applications\Domain\Enums\ResumeTemplate::Classic))->not->toContain('#1e3a8a')->not->toContain('font-size: 9pt; line-height');

    foreach (['classic', 'modern', 'compact', 'nonsense'] as $template) {
        $response = $this->get(route('applications.packages.resume', [$package, 'template' => $template]))->assertOk();
        expect($response->getContent())->toStartWith('%PDF-');
    }
});

it('downloads the letter as text', function (): void {
    $package = prepare(candidateWithProfile(), spanishLaravelJob());

    $response = $this->get(route('applications.packages.letter', $package))->assertOk();

    expect($response->getContent())->toBe($package->cover_letter)
        ->and($response->headers->get('Content-Type'))->toContain('text/plain');
});

it('marks the application as sent when the person says so', function (): void {
    $package = prepare(candidateWithProfile(), spanishLaravelJob());

    $this->post(route('applications.packages.applied', $package));

    $application = Application::query()->sole();
    expect($application->status)->toBe(ApplicationStatus::Applied)->and($application->applied_at)->not->toBeNull();
});

it('keeps packages private', function (): void {
    $package = prepare(candidateWithProfile(), spanishLaravelJob());

    $this->actingAs(User::factory()->create());
    $this->get(route('applications.packages.show', $package))->assertForbidden();
    $this->put(route('applications.packages.update', $package), ['cover_letter' => 'x'])->assertForbidden();
    $this->get(route('applications.packages.letter', $package))->assertForbidden();
    $this->delete(route('applications.packages.destroy', $package))->assertForbidden();
});

it('lists earlier packages and links the latest from My jobs', function (): void {
    $user = candidateWithProfile();
    $job = spanishLaravelJob();
    $first = prepare($user, $job);
    $second = prepare($user, $job);

    $this->get(route('applications.packages.show', $second))->assertInertia(fn (Assert $page) => $page->where('history.0.id', $first->id));
    $this->get(route('applications.index'))->assertInertia(fn (Assert $page) => $page->where('applications.0.package.id', $second->id));
    $this->get(route('jobs.show', $job))->assertInertia(fn (Assert $page) => $page
        ->where('quickApply.packages', 2)
        ->where('quickApply.latest_package_id', $second->id)
        ->where('quickApply.ai_available', false));

    $this->delete(route('applications.packages.destroy', $second))->assertRedirect(route('applications.index'));
    expect(ApplicationPackage::query()->pluck('id')->all())->toBe([$first->id]);
});

it('maps an approved package to common form fields for a future browser extension', function (): void {
    $package = prepare(candidateWithProfile(), spanishLaravelJob());

    $fields = app(ApplicationAssistant::class)->fieldsFor($package->fresh());

    expect($fields)->toMatchArray([
        'full_name' => 'Ana García',
        'first_name' => 'Ana',
        'last_name' => 'García',
        'phone' => '+34 600 000 000',
        'github' => 'https://github.com/ana',
        'salary_expectation' => '60000 EUR',
    ])->and($fields['cover_letter'])->toBe($package->cover_letter);
});

it('stores letters and CVs encrypted and exports them with the personal data', function (): void {
    $user = candidateWithProfile();
    $package = prepare($user, spanishLaravelJob());

    $raw = DB::table('application_packages')->where('id', $package->id)->first(['cover_letter', 'resume']);
    expect($raw->cover_letter)->not->toContain('Globex')->and($raw->resume)->not->toContain('Acme');

    $zip = new ZipArchive;
    $zip->open(app(App\Modules\Identity\Application\Actions\ExportPersonalData::class)->handle($user));
    $data = json_decode((string) $zip->getFromName('data.json'), true);

    expect($data['sections']['applications']['prepared_applications'][0]['cover_letter'])->toBe($package->cover_letter);
});

it('reviews the draft: letter length, company, placeholders, uncovered requirements', function (): void {
    $job = spanishLaravelJob(['company' => 'Globex']);
    $requirements = app(ResolveJobRequirements::class)->handle($job);
    $resume = ResumeData::fromArray(['contact' => ['name' => 'Ana García'], 'skills' => ['PHP']]);
    $reviewer = app(DraftReviewer::class);

    $keys = fn (string $letter): array => array_column(array_filter(
        $reviewer->review($resume, $letter, $job, $requirements, 'es', LetterLength::Short),
        fn (array $warning): bool => ! str_starts_with($warning['key'], 'ats_'),
    ), 'key');

    expect($keys('Hola [Nombre de la empresa], me interesa el puesto.'))
        ->toContain('letter_placeholder', 'letter_length', 'letter_company')
        ->and($keys(str_repeat('Trabajo con Laravel en Globex. ', 60)))
        ->not->toContain('letter_company', 'letter_placeholder')
        ->toContain('letter_length');

    $wellSized = implode(' ', array_fill(0, 150, 'palabra')).' Globex';
    expect($keys($wellSized))->not->toContain('letter_length', 'letter_company', 'letter_placeholder');
});

it('flags required skills the CV never mentions and recomputes them after edits', function (): void {
    $user = candidateWithProfile();
    $package = prepare($user, spanishLaravelJob(['description' => '<p>Requisitos: experiencia con Laravel, PostgreSQL y Kubernetes.</p>']));
    $keys = fn (): array => array_column($package->fresh()->warnings, 'key');

    $this->put(route('applications.packages.update', $package), [
        'resume' => ['summary' => 'Ingeniera backend.', 'skills' => ['PHP']],
    ])->assertSessionHasNoErrors();

    expect(collect($package->fresh()->warnings)->firstWhere('key', 'cv_missing_required')['params']['skills'])->toContain('Kubernetes');

    $this->put(route('applications.packages.update', $package), [
        'resume' => ['summary' => 'Ingeniera backend.', 'skills' => ['PHP', 'Kubernetes']],
    ])->assertSessionHasNoErrors();

    expect($keys())->not->toContain('cv_missing_required');
});

it('reads the CV PDF back like an ATS and reports nothing lost for a complete CV', function (): void {
    $user = candidateWithProfile();
    $package = prepare($user, spanishLaravelJob());

    expect(array_column($package->warnings, 'key'))->not->toContain('ats_unreadable', 'ats_missing');
});

describe('browser extension', function (): void {
    function extensionToken(User $user): string
    {
        test()->actingAs($user)->post(route('extension.store'), ['name' => 'Laptop'])->assertRedirect()->assertSessionHasNoErrors();
        $token = session('extension_token');
        auth()->logout();
        test()->flushSession();

        return $token;
    }

    it('shows a new token once and keeps only its hash', function (): void {
        $user = candidateWithProfile();

        $this->actingAs($user)->post(route('extension.store'), ['name' => 'Laptop']);
        $this->get(route('extension.edit'))->assertInertia(fn ($page) => $page->where('newToken', fn (string $token): bool => str_starts_with($token, 'mtx_'))->has('tokens', 1));
        $this->get(route('extension.edit'))->assertInertia(fn ($page) => $page->where('newToken', null));

        expect(DB::table('extension_tokens')->value('token_hash'))->toHaveLength(64)->not->toStartWith('mtx_');
        $this->post(route('extension.store'), ['name' => ''])->assertSessionHasErrors('name');
        foreach (range(2, 5) as $i) {
            $this->post(route('extension.store'), ['name' => "T{$i}"]);
        }
        $this->post(route('extension.store'), ['name' => 'Sixth'])->assertSessionHasErrors('name');
    });

    it('lists only approved applications and gives their form fields and CV', function (): void {
        $user = candidateWithProfile();
        $approved = prepare($user, spanishLaravelJob());
        $this->post(route('applications.packages.approve', $approved));
        $draft = prepare($user, spanishLaravelJob(['external_id' => 'second', 'title' => 'Another job']));
        $token = extensionToken($user);
        $headers = ['Authorization' => "Bearer {$token}"];

        $list = $this->getJson('/api/extension/packages', $headers)->assertOk()->json('packages');
        expect(array_column($list, 'id'))->toBe([$approved->id]);

        $fields = $this->getJson("/api/extension/packages/{$approved->id}/fields", $headers)->assertOk()->json('fields');
        expect($fields)->toHaveKeys(['full_name', 'email', 'cover_letter']);

        $this->get("/api/extension/packages/{$approved->id}/resume.pdf?template=modern", $headers)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        // A draft is never exposed.
        $this->getJson("/api/extension/packages/{$draft->id}/fields", $headers)->assertNotFound();
        expect(DB::table('extension_tokens')->value('last_used_at'))->not->toBeNull();
    });

    it('rejects missing, wrong and revoked tokens, and other people\'s packages', function (): void {
        $owner = candidateWithProfile();
        $package = prepare($owner, spanishLaravelJob());
        $this->post(route('applications.packages.approve', $package));
        $ownerToken = extensionToken($owner);

        $this->getJson('/api/extension/packages')->assertUnauthorized();
        $this->getJson('/api/extension/packages', ['Authorization' => 'Bearer mtx_nope'])->assertUnauthorized();
        $this->getJson('/api/extension/packages', ['Authorization' => 'Bearer notmine'])->assertUnauthorized();

        $other = User::factory()->create();
        $otherToken = extensionToken($other);
        $this->getJson("/api/extension/packages/{$package->id}/fields", ['Authorization' => "Bearer {$otherToken}"])->assertNotFound();

        $this->actingAs($owner)->delete(route('extension.destroy', DB::table('extension_tokens')->where('user_id', $owner->id)->value('id')))->assertRedirect();
        auth()->logout();
        $this->getJson('/api/extension/packages', ['Authorization' => "Bearer {$ownerToken}"])->assertUnauthorized();

        $tokenId = DB::table('extension_tokens')->where('user_id', $other->id)->value('id');
        $this->actingAs($owner)->delete(route('extension.destroy', $tokenId))->assertForbidden();
    });
});
