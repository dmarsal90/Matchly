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

describe('practising answers', function (): void {
    function practiceKey(Application $application, string $skill = 'Laravel'): string
    {
        $question = collect($application->refresh()->interviewPrep->content['questions'])->firstWhere('skill', $skill);

        return App\Modules\Applications\Application\Actions\SaveInterviewAnswer::keyOf($question['question']);
    }

    function preparedFor(User $user): Application
    {
        $application = appliedFor($user);
        test()->actingAs($user)->post(route('applications.interview-prep.store', $application), []);

        return $application;
    }

    it('saves an answer encrypted, sends it back with the pack and drops it when emptied', function (): void {
        $user = candidateWithProfile();
        $application = preparedFor($user);
        $key = practiceKey($application);

        $this->actingAs($user)->put(route('applications.interview-prep.answer', $application), ['key' => $key, 'answer' => '  I built the API with Laravel.  '])->assertRedirect()->assertSessionHasNoErrors();

        expect(DB::table('interview_answers')->value('answer'))->not->toContain('Laravel')
            ->and($application->interviewAnswers()->sole()->answer)->toBe('I built the API with Laravel.');
        $this->get(route('applications.interview-prep.show', $application))->assertInertia(fn (Assert $page) => $page
            ->where("answers.{$key}.answer", 'I built the API with Laravel.')
            ->where('prep.content.questions.0.key', fn (string $value): bool => strlen($value) === 40));

        $this->put(route('applications.interview-prep.answer', $application), ['key' => $key, 'answer' => ''])->assertRedirect();
        expect($application->interviewAnswers()->count())->toBe(0);
    });

    it('only accepts questions of the pack and only from its owner', function (): void {
        $user = candidateWithProfile();
        $application = preparedFor($user);
        $key = practiceKey($application);

        $this->actingAs($user)->put(route('applications.interview-prep.answer', $application), ['key' => sha1('not a question'), 'answer' => 'x'])->assertNotFound();
        $this->put(route('applications.interview-prep.answer', $application), ['key' => 'nonsense', 'answer' => 'x'])->assertSessionHasErrors('key');
        $this->put(route('applications.interview-prep.answer', $application), ['key' => $key, 'answer' => str_repeat('a', 4001)])->assertSessionHasErrors('answer');

        $this->actingAs(User::factory()->create())->put(route('applications.interview-prep.answer', $application), ['key' => $key, 'answer' => 'x'])->assertForbidden();
    });

    it('checks the answer with plain rules and forgets the feedback when the text changes', function (): void {
        $user = candidateWithProfile();
        $application = preparedFor($user);
        $key = practiceKey($application);
        $long = 'At Acme I used Laravel to build a billing API: '.str_repeat('we designed the endpoints, wrote the tests and shipped it step by step. ', 4).'It cut invoice errors by 40%.';

        $this->actingAs($user)->put(route('applications.interview-prep.answer', $application), ['key' => $key, 'answer' => 'Laravel.']);
        $this->post(route('applications.interview-prep.feedback', $application), ['key' => $key])->assertRedirect();
        $short = $application->interviewAnswers()->sole();
        expect($short->feedback_source->value)->toBe('rules')
            ->and(array_column($short->feedback['improvements'], 'key'))->toBe(['too_short', 'add_result'])
            ->and(array_column($short->feedback['strengths'], 'key'))->toBe(['names_skill']);

        $this->put(route('applications.interview-prep.answer', $application), ['key' => $key, 'answer' => $long]);
        expect($application->interviewAnswers()->sole()->feedback)->toBeNull();

        $this->post(route('applications.interview-prep.feedback', $application), ['key' => $key]);
        $good = $application->interviewAnswers()->sole();
        expect(array_column($good->feedback['strengths'], 'key'))->toBe(['good_length', 'has_numbers', 'names_skill'])
            ->and($good->feedback['improvements'])->toBe([]);

        $this->post(route('applications.interview-prep.feedback', $application), ['key' => sha1('nope')])->assertNotFound();
    });

    it('asks the AI only to comment, never sends more than the answer and the skills, and falls back to the rules', function (): void {
        config(['ai.enabled' => true, 'ai.fallback_order' => ['anthropic'], 'ai.providers.anthropic.api_key' => 'server-key']);
        Http::fake(['api.anthropic.com/*' => Http::sequence()->push(['content' => [['type' => 'tool_use', 'name' => 'submit_result', 'input' => [
            'strengths' => ['You open with a concrete project.'],
            'improvements' => ['Say what the measurable result was.'],
        ]]], 'usage' => ['input_tokens' => 200, 'output_tokens' => 80]])->whenEmpty(Http::response('boom', 500))]);

        $user = candidateWithProfile();
        $application = preparedFor($user);
        $key = practiceKey($application);
        $this->actingAs($user)->put(route('applications.interview-prep.answer', $application), ['key' => $key, 'answer' => 'I built an API with Laravel.']);

        $this->post(route('applications.interview-prep.feedback', $application), ['key' => $key, 'use_ai' => true])->assertSessionHasErrors('ai_consent');
        $this->post(route('applications.interview-prep.feedback', $application), ['key' => $key, 'use_ai' => true, 'ai_consent' => '1'])->assertSessionHasNoErrors();

        $feedback = $application->interviewAnswers()->sole();
        expect($feedback->feedback_source->value)->toBe('ai')
            ->and($feedback->feedback)->toBe(['strengths' => [['text' => 'You open with a concrete project.']], 'improvements' => [['text' => 'Say what the measurable result was.']]]);
        Http::assertSent(fn (Request $request): bool => str_contains((string) json_encode($request->data()), 'candidate_skills') && ! str_contains((string) json_encode($request->data()), 'company_facts'));

        $this->post(route('applications.interview-prep.feedback', $application), ['key' => $key, 'use_ai' => true])->assertSessionHasNoErrors();
        expect($application->interviewAnswers()->sole()->feedback_source->value)->toBe('rules');
    });

    it('includes the practised answers in the personal data export', function (): void {
        $user = candidateWithProfile();
        $application = preparedFor($user);
        $this->actingAs($user)->put(route('applications.interview-prep.answer', $application), ['key' => practiceKey($application), 'answer' => 'My practised answer']);

        $export = app(App\Modules\Applications\Application\Privacy\ApplicationDataContributor::class)->export($user->id);

        expect(json_encode($export->data))->toContain('My practised answer');
    });
});

describe('mock interview', function (): void {
    it('goes through the pack questions and reviews only the answered ones', function (): void {
        $user = candidateWithProfile();
        $application = appliedFor($user);
        $this->actingAs($user)->get(route('applications.interview-prep.mock', $application))->assertRedirect(route('applications.interview-prep.show', $application));

        $this->post(route('applications.interview-prep.store', $application), []);
        $key = practiceKey($application);
        $this->get(route('applications.interview-prep.mock', $application))->assertInertia(fn (Assert $page) => $page
            ->component('applications/MockInterview')
            ->where('secondsPerAnswer', 180)
            ->has('questions')
            ->where('questions.0.key', fn (string $value): bool => strlen($value) === 40));

        $this->put(route('applications.interview-prep.answer', $application), ['key' => $key, 'answer' => 'Laravel.']);
        $this->post(route('applications.interview-prep.mock.review', $application))->assertRedirect();

        $answers = $application->interviewAnswers()->get();
        expect($answers)->toHaveCount(1)
            ->and($answers->first()->feedback_source->value)->toBe('rules')
            ->and(array_column($answers->first()->feedback['improvements'], 'key'))->toContain('too_short');
    });

    it('is only for the owner', function (): void {
        $application = preparedFor(candidateWithProfile());

        $this->actingAs(User::factory()->create())->get(route('applications.interview-prep.mock', $application))->assertForbidden();
        $this->post(route('applications.interview-prep.mock.review', $application))->assertForbidden();
    });
});
