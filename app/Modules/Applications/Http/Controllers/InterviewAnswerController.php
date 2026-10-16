<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Actions\GiveAnswerFeedback;
use App\Modules\Applications\Application\Actions\SaveInterviewAnswer;
use App\Modules\Applications\Http\Requests\AnswerFeedbackRequest;
use App\Modules\Applications\Http\Requests\SaveInterviewAnswerRequest;
use App\Modules\Applications\Infrastructure\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Practising the questions of an interview pack: answers are private notes, feedback is optional. */
final class InterviewAnswerController
{
    public function update(SaveInterviewAnswerRequest $request, Application $application, SaveInterviewAnswer $save): RedirectResponse
    {
        Gate::authorize('update', $application);
        $application->load('interviewPrep');
        abort_if(SaveInterviewAnswer::questionFor($application, $request->key()) === null, 404);

        $save->handle($application, $request->key(), $request->answer());

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
