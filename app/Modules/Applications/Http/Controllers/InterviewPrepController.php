<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\AI\Application\AiGateway;
use App\Modules\Applications\Application\Actions\SaveInterviewAnswer;
use App\Modules\Applications\Application\Actions\StartInterviewPrep;
use App\Modules\Applications\Http\Requests\StartInterviewPrepRequest;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\InterviewAnswer;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Interview preparation for one application. Nothing is sent anywhere: it is notes for the person. */
final class InterviewPrepController
{
    public function show(Application $application, #[CurrentUser] User $user, AiGateway $ai): Response
    {
        Gate::authorize('update', $application);

        $application->load('job', 'interviewPrep');
        $job = $application->job;
        $prep = $application->interviewPrep;

        return Inertia::render('applications/InterviewPrep', [
            'application' => ['id' => $application->id, 'status' => $application->status->value],
            'job' => [
                'id' => $job->id,
                'slug' => Str::slug(Str::limit($job->title.' '.$job->company, 80, '')),
                'title' => $job->title,
                'company' => $job->company,
            ],
            'prep' => $prep === null ? null : [
                'status' => $prep->status->value,
                'source' => $prep->source?->value,
                'content' => $prep->content === null ? null : [...$prep->content, 'questions' => array_map(fn (array $question): array => [...$question, 'key' => SaveInterviewAnswer::keyOf($question['question'])], $prep->content['questions'])],
                'warnings' => $prep->warnings ?? [],
                'updated_at' => $prep->updated_at->toIso8601String(),
            ],
            'answers' => $application->interviewAnswers()->get()->mapWithKeys(fn (InterviewAnswer $answer): array => [$answer->question_key => [
                'answer' => $answer->answer,
                'feedback' => $answer->feedback,
                'feedback_source' => $answer->feedback_source?->value,
            ]])->all(),
            'options' => [
                'ai_available' => $ai->available($user),
                'ai_consented' => $user->hasConsented(ConsentPurpose::AiProcessing),
            ],
        ]);
    }

    public function store(StartInterviewPrepRequest $request, Application $application, StartInterviewPrep $start): RedirectResponse
    {
        Gate::authorize('update', $application);

        $start->handle($application, $request->boolean('use_ai'));

        return back();
    }
}
