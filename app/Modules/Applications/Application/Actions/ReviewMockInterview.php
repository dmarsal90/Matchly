<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\InterviewAnswer;

/**
 * Closes a mock interview: every practised question gets the plain checks, so
 * the summary can show where the answers are weakest. Nothing leaves the app.
 */
final readonly class ReviewMockInterview
{
    public function __construct(private GiveAnswerFeedback $feedback) {}

    /**
     * @return int how many answers were reviewed
     */
    public function handle(Application $application): int
    {
        $application->loadMissing('interviewPrep');
        $reviewed = 0;

        foreach ($application->interviewPrep?->content['questions'] ?? [] as $question) {
            $key = SaveInterviewAnswer::keyOf($question['question']);
            if (InterviewAnswer::query()->where('application_id', $application->id)->where('question_key', $key)->exists()) {
                $this->feedback->handle($application, $key, false);
                $reviewed++;
            }
        }

        return $reviewed;
    }
}
