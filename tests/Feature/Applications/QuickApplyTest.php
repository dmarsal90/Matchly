<?php

declare(strict_types=1);

use App\Modules\Applications\Domain\Contracts\ApplicationAssistant;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Domain\Enums\PackageStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function candidateWithProfile(): User
{
    $user = User::factory()->create(['name' => 'Ana García']);
    $user->profile()->create([
        'headline' => 'Backend engineer',
        'phone' => '+34 600 000 000',
        'city' => 'Valencia',
        'country' => 'ES',
        'github_username' => 'ana',
        'years_experience' => 6,
        'languages' => [['code' => 'es', 'level' => 'native']],
        'salary_min' => 60000,
        'salary_currency' => 'EUR',
        'salary_period' => 'year',
    ]);
    $user->profileImports()->create([
        'source' => SkillSource::Linkedin,
        'status' => ImportStatus::Confirmed,
        'confirmed_at' => now(),
        'payload' => [
            'profile' => ['summary' => 'Backend developer focused on APIs.'],
            'positions' => [[
                'title' => 'Senior Backend Engineer', 'company' => 'Acme', 'location' => null,
                'description' => "Mentored two junior developers.\nBuilt REST APIs with Laravel and PostgreSQL.",
                'started_on' => '2021-03', 'finished_on' => null,
            ]],
            'education' => [['school' => 'Universitat de València', 'degree' => 'Computer Engineering', 'started_on' => '2012', 'finished_on' => '2016']],
            'certifications' => [],
            'projects' => [],
        ],
    ]);
    foreach (['Figma' => 'figma', 'PHP' => 'php', 'Laravel' => 'laravel', 'PostgreSQL' => 'postgresql'] as $name => $slug) {
        $user->skills()->create(['source' => SkillSource::Github, 'skill' => $slug, 'name' => $name, 'level' => 4]);
    }

    return $user;
}

function spanishLaravelJob(array $attributes = []): JobListing
{
    return JobListing::factory()->create([
        'title' => 'Desarrollador Backend Senior',
        'company' => 'Globex',
        'description' => '<p>Buscamos una persona con experiencia en Laravel y PostgreSQL para el equipo de la empresa. Valoramos Kubernetes.</p>',
        'tags' => [],
        ...$attributes,
    ]);
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
        ->and($package->warnings)->toBe([])
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

    expect(array_column($package->warnings, 'key'))->toBe(['empty_profile']);
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
            ->and($package->warnings)->toBe([
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
        ->and($package->warnings)->toBe([['key' => 'ai_unavailable', 'params' => ['reason' => 'failed']]]);
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
        ->and(array_column($package->warnings, 'key'))->toBe(['unbacked_skills', 'years_claim']);
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
