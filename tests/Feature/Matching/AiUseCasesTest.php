<?php

declare(strict_types=1);

use App\Modules\AI\Infrastructure\Models\AiUsage;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Infrastructure\Models\JobMatch;
use App\Modules\Matching\Infrastructure\Queue\RefineJobRequirementsJob;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config([
        'ai.enabled' => true,
        'ai.fallback_order' => ['anthropic'],
        'ai.providers.anthropic.api_key' => 'server-key',
    ]);
});

function fakeAnthropic(array $input): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'tool_use', 'name' => 'submit_result', 'input' => $input]],
        'usage' => ['input_tokens' => 800, 'output_tokens' => 300],
    ])]);
}

describe('job requirements', function (): void {
    it('refines requirements with AI, normalising skills, and rescores the job', function (): void {
        fakeAnthropic(json_decode((string) file_get_contents(base_path('tests/Fixtures/ai/job_requirements.json')), true));
        $user = User::factory()->create();
        $user->skills()->create(['source' => SkillSource::Github, 'skill' => 'vue', 'name' => 'Vue', 'level' => 4]);
        $job = JobListing::factory()->create(['title' => 'Frontend Engineer', 'description' => '<p>Vue.js, TypeScript.</p>']);

        RefineJobRequirementsJob::dispatch($job->id);

        $requirements = $job->fresh()->requirements;
        expect($requirements['source'])->toBe('ai')
            ->and($requirements['prompt_version'])->toBe('v1')
            // "Vue.js" and "vue" are one skill.
            ->and(array_column($requirements['required_skills'], 'skill'))->toBe(['vue', 'typescript'])
            ->and($requirements['required_skills'][0]['min_years'])->toEqual(3)
            ->and($requirements['location']['regions'])->toBe(['EU'])
            ->and($requirements['asks_for']['cover_letter'])->toBeTrue()
            ->and(JobMatch::query()->where('user_id', $user->id)->where('job_listing_id', $job->id)->exists())->toBeTrue()
            ->and(AiUsage::query()->sole()->user_id)->toBeNull();

        // Done once per job and prompt version.
        Http::fake();
        RefineJobRequirementsJob::dispatch($job->id);
        Http::assertNothingSent();
    });

    it('keeps rule-based requirements when the AI is unavailable', function (): void {
        Http::fake(['api.anthropic.com/*' => Http::response('down', 500)]);
        $job = JobListing::factory()->create(['description' => '<p>PHP and Laravel.</p>']);

        RefineJobRequirementsJob::dispatch($job->id);

        expect($job->fresh()->requirements)->toBeNull();
    });
});

describe('CV structuring', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
    });

    function userWithExtractedCv(): User
    {
        $user = User::factory()->create();
        test()->actingAs($user)->post('/profile/cv', [
            'file' => UploadedFile::fake()->createWithContent('cv.pdf', makePdf('Ana Garcia. Senior Backend Engineer at Acme Corp 2021 - present. Built REST APIs with Laravel and PostgreSQL. Universidad de Valencia 2012 2016. AWS Certified Developer.')),
            'consent' => '1',
        ]);

        return $user;
    }

    it('requires a separate consent to use AI', function (): void {
        userWithExtractedCv();

        $this->post('/profile/imports/cv', ['use_ai' => true])->assertSessionHasErrors('ai_consent');
    });

    it('structures the CV with AI and drops anything not in the text', function (): void {
        fakeAnthropic([
            'headline' => 'Senior Backend Engineer',
            'positions' => [
                ['title' => 'Senior Backend Engineer', 'company' => 'Acme Corp', 'description' => 'Built REST APIs with Laravel and PostgreSQL.', 'started_on' => '2021', 'finished_on' => null],
                // Invented: not in the CV.
                ['title' => 'CTO', 'company' => 'Google', 'description' => 'Led 500 engineers.', 'started_on' => '2019', 'finished_on' => '2020'],
                ['title' => 'Senior Backend Engineer', 'company' => 'Initech', 'description' => 'Led a revolutionary blockchain metaverse transformation.', 'started_on' => '1999', 'finished_on' => null],
            ],
            'education' => [['school' => 'Universidad de Valencia', 'degree' => 'PhD in Physics', 'started_on' => '2012', 'finished_on' => '2016']],
            'certifications' => [['name' => 'AWS Certified Developer', 'authority' => 'Amazon'], ['name' => 'PMP', 'authority' => null]],
            'projects' => [],
            'skills' => ['Laravel', 'PostgreSQL', 'Kubernetes'],
        ]);
        $user = userWithExtractedCv();

        $this->post('/profile/imports/cv', ['use_ai' => true, 'ai_consent' => '1'])->assertRedirect();

        $import = ProfileImport::query()->sole();
        $payload = $import->payload;
        expect($import->status)->toBe(ImportStatus::PendingReview)
            ->and($user->hasConsented(ConsentPurpose::AiProcessing))->toBeTrue()
            ->and($payload['ai']['provider'])->toBe('anthropic')
            ->and($payload['profile']['headline'])->toBe('Senior Backend Engineer')
            ->and($payload['positions'])->toHaveCount(2)
            ->and($payload['positions'][0])->toMatchArray(['company' => 'Acme Corp', 'started_on' => '2021'])
            // Unknown company, embellished summary and a year not in the CV are dropped field by field.
            ->and($payload['positions'][1])->toMatchArray(['company' => null, 'description' => null, 'started_on' => null])
            // The degree is not in the text.
            ->and($payload['education'][0])->toMatchArray(['school' => 'Universidad de Valencia', 'degree' => null])
            ->and(array_column($payload['certifications'], 'name'))->toBe(['AWS Certified Developer'])
            ->and(collect($payload['skills'])->pluck('skill')->sort()->values()->all())->toBe(['aws', 'laravel', 'postgresql', 'rest-api']);

        $this->get(route('skills.imports.show', $import))->assertInertia(fn (Assert $page) => $page->where('review.data.ai.provider', 'anthropic'));
    });

    it('falls back to keyword detection when the AI fails', function (): void {
        Http::fake(['api.anthropic.com/*' => Http::response('nope', 500)]);
        userWithExtractedCv();

        $this->post('/profile/imports/cv', ['use_ai' => true, 'ai_consent' => '1']);

        $payload = ProfileImport::query()->sole()->payload;
        expect($payload['ai_error'])->toBe('failed')
            ->and(collect($payload['skills'])->pluck('skill')->all())->toContain('laravel', 'postgresql');
    });

    it('skips the AI when no provider is available', function (): void {
        config(['ai.providers.anthropic.api_key' => null]);
        Http::fake();
        userWithExtractedCv();

        $this->post('/profile/imports/cv', ['use_ai' => true, 'ai_consent' => '1'])->assertRedirect();

        expect(ProfileImport::query()->sole()->status)->toBe(ImportStatus::PendingReview);
        Http::assertNothingSent();
    });
});

it('adds a verified AI summary to GitHub imports when the user opts in', function (): void {
    config(['github.token' => null, 'github.api_url' => 'https://api.github.com']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'name' => 'submit_result', 'input' => [
                'summary' => 'Builds shop applications with PHP and Vue.',
                'technologies' => ['PHP', 'Vue', 'Rust'],
                'project_types' => ['web application'],
                'highlights' => [['repository' => 'shop', 'note' => 'A shop.'], ['repository' => 'secret', 'note' => 'Invented.']],
            ]]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]),
        'api.github.com/users/octocat' => Http::response(['login' => 'octocat', 'type' => 'User']),
        'api.github.com/users/octocat/repos*' => Http::response([[
            'name' => 'shop', 'fork' => false, 'owner' => ['login' => 'octocat'], 'description' => 'An online shop',
            'created_at' => '2024-01-01T00:00:00Z', 'pushed_at' => now()->subMonth()->toIso8601String(), 'topics' => [],
        ]]),
        'api.github.com/repos/octocat/shop/languages' => Http::response(['PHP' => 100, 'Vue' => 50]),
        'api.github.com/repos/octocat/shop/contents/' => Http::response([]),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user)->post('/profile/imports/github', ['username' => 'octocat', 'consent' => '1', 'ai_summary' => '1'])->assertSessionHasNoErrors();

    $summary = ProfileImport::query()->sole()->payload['summary'];
    expect($user->hasConsented(ConsentPurpose::AiProcessing))->toBeTrue()
        ->and($summary['technologies'])->toBe(['PHP', 'Vue'])
        ->and($summary['highlights'])->toBe([['repository' => 'shop', 'note' => 'A shop.']]);
});

describe('AI settings', function (): void {
    it('stores the user\'s own key encrypted and never shows it', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings/ai/key', ['anthropic_api_key' => 'sk-ant-api03-abcdefghijklmnop'])->assertSessionHasNoErrors();

        $raw = (string) DB::table('users')->where('id', $user->id)->value('anthropic_api_key');
        expect($raw)->not->toContain('sk-ant')
            ->and($user->fresh()->anthropic_api_key)->toBe('sk-ant-api03-abcdefghijklmnop')
            ->and($user->fresh()->toArray())->not->toHaveKey('anthropic_api_key');

        $this->get('/settings/ai')->assertInertia(fn (Assert $page) => $page
            ->component('settings/Ai')
            ->where('hasOwnKey', true)
            ->where('keyHint', '…mnop')
            ->missing('anthropic_api_key'));

        $this->delete('/settings/ai/key');
        expect($user->fresh()->anthropic_api_key)->toBeNull();
    });

    it('rejects anything that is not an Anthropic key', function (): void {
        $this->actingAs(User::factory()->create())->put('/settings/ai/key', ['anthropic_api_key' => 'hello world'])->assertSessionHasErrors('anthropic_api_key');
    });

    it('shows this month\'s usage against the budget', function (): void {
        config(['ai.budget.user_monthly_usd' => 2.0]);
        $user = User::factory()->create();
        AiUsage::query()->create(['user_id' => $user->id, 'task' => 'structure_cv', 'provider' => 'anthropic', 'model' => 'x', 'key_owner' => 'server', 'cost_usd' => 0.5, 'success' => true]);
        AiUsage::query()->create(['user_id' => $user->id, 'task' => 'summarize_github', 'provider' => 'anthropic', 'model' => 'x', 'key_owner' => 'user', 'cost_usd' => 0.25, 'success' => true]);
        AiUsage::query()->create(['user_id' => $user->id, 'task' => 'structure_cv', 'provider' => 'anthropic', 'model' => 'x', 'key_owner' => 'server', 'cost_usd' => 1, 'success' => true, 'created_at' => now()->subMonths(2)]);

        $this->actingAs($user)->get('/settings/ai')->assertInertia(fn (Assert $page) => $page
            ->where('usage.calls', 2)
            ->where('usage.budget_used_usd', 0.5)
            ->where('usage.budget_usd', 2)
            ->where('usage.own_key_cost_usd', 0.25)
            ->where('usage.by_task', ['structure_cv' => 1, 'summarize_github' => 1]));
    });
});

it('exports AI usage and match explanations with the personal data', function (): void {
    $user = User::factory()->create();
    $job = JobListing::factory()->create();
    AiUsage::query()->create(['user_id' => $user->id, 'task' => 'structure_cv', 'provider' => 'anthropic', 'model' => 'x', 'key_owner' => 'server', 'cost_usd' => 0.01, 'success' => true]);
    JobMatch::query()->create(['user_id' => $user->id, 'job_listing_id' => $job->id, 'score' => 81, 'level' => 'high', 'eligible' => true, 'result' => ['score' => 81], 'computed_at' => now()]);

    $zip = new ZipArchive;
    $zip->open(app(App\Modules\Identity\Application\Actions\ExportPersonalData::class)->handle($user));
    $data = json_decode((string) $zip->getFromName('data.json'), true);

    expect($data['sections']['ai_usage']['calls'][0]['task'])->toBe('structure_cv')
        ->and($data['sections']['matches']['scores'][0]['score'])->toBe(81);
});
