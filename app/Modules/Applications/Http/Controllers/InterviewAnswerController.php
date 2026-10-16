<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Actions\GiveAnswerFeedback;
use App\Modules\Applications\Application\Actions\ReviewMockInterview;
use App\Modules\Applications\Application\Actions\SaveInterviewAnswer;
use App\Modules\Applications\Domain\Enums\InterviewPrepStatus;
use App\Modules\Applications\Http\Requests\AnswerFeedbackRequest;
use App\Modules\Applications\Http\Requests\SaveInterviewAnswerRequest;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\InterviewAnswer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Practising the questions of an interview pack: answers are private notes, feedback is optional. */
final class InterviewAnswerController
{
    /** A comfortable time to answer out loud or in writing; shown as a guide, never enforced. */
    private const int SECONDS_PER_ANSWER = 180;

    public function update(SaveInterviewAnswerRequest $request, Application $application, SaveInterviewAnswer $save): RedirectResponse
    {
        Gate::authorize('update', $application);
        $application->load('interviewPrep');
        abort_if(SaveInterviewAnswer::questionFor($application, $request->key()) === null, 404);

        $save->handle($application, $request->key(), $request->answer());

        return back();
    }

    /** The mock interview: the pack's questions one at a time. */
    public function mock(Application $application): Response|RedirectResponse
    {
        Gate::authorize('update', $application);
        $application->load('job', 'interviewPrep', 'interviewAnswers');
        $prep = $application->interviewPrep;

        if ($prep === null || $prep->status !== InterviewPrepStatus::Ready || $prep->content === null) {
            return to_route('applications.interview-prep.show', $application);
        }

        return Inertia::render('applications/MockInterview', [
            'application' => ['id' => $application->id],
            'job' => ['title' => $application->job->title, 'company' => $application->job->company],
            'questions' => array_map(fn (array $question): array => [
                'key' => SaveInterviewAnswer::keyOf($question['question']),
                'category' => $question['category'],
                'question' => $question['question'],
            ], $prep->content['questions']),
            'answers' => $application->interviewAnswers->mapWithKeys(fn (InterviewAnswer $answer): array => [$answer->question_key => [
                'answer' => $answer->answer,
                'feedback' => $answer->feedback,
                'feedback_source' => $answer->feedback_source?->value,
            ]])->all(),
            'secondsPerAnswer' => self::SECONDS_PER_ANSWER,
        ]);
    }

    public function review(Application $application, ReviewMockInterview $review): RedirectResponse
    {
        Gate::authorize('update', $application);

        $review->handle($application);

        return back();
    }

    public function feedback(AnswerFeedbackRequest $request, Application $application, GiveAnswerFeedback $give): RedirectResponse
    {
        Gate::authorize('update', $application);

        $result = $give->handle($application, $request->key(), $request->boolean('use_ai'));
        abort_if($result === null, 404);

        if ($result['aiError'] !== null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('interview_prep.practice.ai_unavailable', ['reason' => __("ai.errors.{$result['aiError']}")])]);
        }

        return back();
    }
}
