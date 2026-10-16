<?php

declare(strict_types=1);

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\InterviewPrep;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function appliedFor(User $user): Application
{
    $application = new Application;
    $application->forceFill([
        'user_id' => $user->id,
        'job_listing_id' => spanishLaravelJob()->id,
        'status' => ApplicationStatus::Applied,
        'status_changed_at' => now(),
        'applied_at' => now(),
    ])->save();

    return $application;
}

it('builds the pack from templates and the CV when AI is off', function (): void {
    $user = candidateWithProfile();
    $application = appliedFor($user);

    $this->actingAs($user)->post(route('applications.interview-prep.store', $application), [])->assertRedirect()->assertSessionHasNoErrors();

    $prep = $application->interviewPrep()->firstOrFail();
    $content = $prep->content;
    $technical = collect($content['questions'])->firstWhere('skill', 'Laravel');

    expect($prep->status->value)->toBe('ready')
        ->and($prep->source->value)->toBe('rules')
        // The outline quotes the person's own bullet and leaves task/result for them.
        ->and($technical['star']['action'])->toContain('Laravel')
        ->and($technical['star']['task'])->toBeNull()
        ->and($technical['star']['result'])->toBeNull()
        // Kubernetes is asked for but not in the CV: an honest gap, never a claim.
        ->and(array_column($content['gaps'], 'skill'))->toBe(['Kubernetes'])
        ->and($content['gaps'][0]['required'])->toBeFalse()
        ->and($content['gaps'][0]['answer'])->toContain('Laravel')
        ->and(collect($content['questions'])->pluck('skill')->filter()->all())->not->toContain('Kubernetes')
        ->and($content['ask'])->toHaveCount(3);
});

it('shows the pack only to its owner', function (): void {
    $user = candidateWithProfile();
    $application = appliedFor($user);
    $this->actingAs($user)->post(route('applications.interview-prep.store', $application), []);

    $this->actingAs($user)->get(route('applications.interview-prep.show', $application))->assertInertia(fn (Assert $page) => $page
        ->component('applications/InterviewPrep')
        ->where('prep.status', 'ready')
        ->has('prep.content.questions'));

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('applications.interview-prep.show', $application))->assertForbidden();
    $this->actingAs($stranger)->post(route('applications.interview-prep.store', $application), [])->assertForbidden();
});

it('needs consent before sending the profile to an AI provider', function (): void {
    $user = candidateWithProfile();
    $application = appliedFor($user);

    $this->actingAs($user)->post(route('applications.interview-prep.store', $application), ['use_ai' => true])->assertSessionHasErrors('ai_consent');
    expect(InterviewPrep::query()->count())->toBe(0);
});

describe('with AI', function (): void {
    beforeEach(function (): void {
        config(['ai.enabled' => true, 'ai.fallback_order' => ['anthropic'], 'ai.providers.anthropic.api_key' => 'server-key']);
    });

    it('drops outlines the CV does not back and ties gap answers to the real gaps', function (): void {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'submit_result', 'input' => [
            'questions' => [
                ['category' => 'technical', 'question' => 'How have you used Laravel?', 'skill' => 'Laravel', 'situation' => 'At Acme', 'task' => '', 'action' => 'Built REST APIs with Laravel and PostgreSQL.', 'result' => ''],
                ['category' => 'technical', 'question' => 'Tell me about Golang.', 'skill' => 'Go', 'situation' => '', 'task' => '', 'action' => 'I wrote Golang services at scale.', 'result' => ''],
                ['category' => 'motivation', 'question' => 'Why Globex?', 'skill' => '', 'situation' => '', 'task' => '', 'action' => '', 'result' => ''],
            ],
            'gaps' => [
                ['skill' => 'Kubernetes', 'answer' => 'I have not used Kubernetes professionally; my closest experience is PostgreSQL operations.'],
                ['skill' => 'Rust', 'answer' => 'An invented gap that is not in the posting.'],
            ],
            'ask' => ['What does the on-call rotation look like?'],
        ]]], 'usage' => ['input_tokens' => 900, 'output_tokens' => 300]])]);

        $user = candidateWithProfile();
        $application = appliedFor($user);

        $this->actingAs($user)->post(route('applications.interview-prep.store', $application), ['use_ai' => true, 'ai_consent' => '1'])->assertSessionHasNoErrors();

        $prep = $application->interviewPrep()->firstOrFail();
        $questions = collect($prep->content['questions']);

        expect($prep->source->value)->toBe('ai')
            ->and($questions->firstWhere('skill', 'Laravel')['star']['action'])->toBe('Built REST APIs with Laravel and PostgreSQL.')
            // Golang is not in the CV: the outline is emptied, the question stays.
            ->and($questions->firstWhere('skill', 'Go')['star']['action'])->toBeNull()
            ->and(array_column($prep->content['gaps'], 'skill'))->toBe(['Kubernetes'])
            ->and($prep->content['gaps'][0]['answer'])->toContain('closest experience is PostgreSQL')
            ->and($prep->content['ask'])->toBe(['What does the on-call rotation look like?'])
            ->and($prep->warnings)->toEqual([['key' => 'star_removed', 'params' => ['count' => '1']]]);

        Http::assertSent(fn (Request $request): bool => str_contains((string) json_encode($request->data()), 'gap_skills'));
    });

    it('falls back to templates when the AI fails', function (): void {
        Http::fake(['api.anthropic.com/*' => Http::response('boom', 500)]);

        $user = candidateWithProfile();
        $application = appliedFor($user);
        $this->actingAs($user)->post(route('applications.interview-prep.store', $application), ['use_ai' => true, 'ai_consent' => '1']);

        $prep = $application->interviewPrep()->firstOrFail();
        expect($prep->status->value)->toBe('ready')
            ->and($prep->source->value)->toBe('rules')
            ->and($prep->warnings[0]['key'])->toBe('ai_unavailable');
    });
});
