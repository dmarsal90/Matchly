<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Privacy;

use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationEvent;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Applications\Infrastructure\Models\CompanyNote;
use App\Modules\Applications\Infrastructure\Models\InterviewAnswer;
use App\Modules\Applications\Infrastructure\Models\InterviewPrep;
use App\Modules\Applications\Infrastructure\Models\SharedReport;
use App\Modules\Identity\Domain\Contracts\PersonalDataContributor;
use App\Modules\Identity\Domain\Data\PersonalData;
use Illuminate\Support\Facades\DB;

final class ApplicationDataContributor implements PersonalDataContributor
{
    public function export(int $userId): PersonalData
    {
        $applications = Application::query()->with(['job:id,title,company,apply_url', 'events'])->where('user_id', $userId)->orderBy('id')->get();

        return new PersonalData('applications', [
            'applications' => $applications->map(fn (Application $application): array => [
                'job' => ['title' => $application->job->title, 'company' => $application->job->company, 'url' => $application->job->apply_url],
                'status' => $application->status->value,
                'notes' => $application->notes,
                'applied_at' => $application->applied_at?->toIso8601String(),
                'history' => $application->events->map(fn (ApplicationEvent $event): array => [
                    'type' => $event->type->value,
                    'status' => $event->status?->value,
                    'round' => $event->round,
                    'note' => $event->note,
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                ])->values()->all(),
                'created_at' => $application->created_at->toIso8601String(),
            ])->values()->all(),
            'interview_preps' => InterviewPrep::query()->with('application.job:id,title,company')->whereHas('application', fn ($application) => $application->where('user_id', $userId))->orderBy('id')->get()
                ->map(fn (InterviewPrep $prep): array => [
                    'job' => "{$prep->application->job->title} · {$prep->application->job->company}",
                    'status' => $prep->status->value,
                    'content' => $prep->content,
                ])->values()->all(),
            'interview_answers' => InterviewAnswer::query()->with('application.job:id,title,company')->whereHas('application', fn ($application) => $application->where('user_id', $userId))->orderBy('id')->get()
                ->map(fn (InterviewAnswer $answer): array => [
                    'job' => "{$answer->application->job->title} · {$answer->application->job->company}",
                    'answer' => $answer->answer,
                    'feedback' => $answer->feedback,
                    'updated_at' => $answer->updated_at->toIso8601String(),
                ])->values()->all(),
            'shared_reports' => SharedReport::query()->where('user_id', $userId)->orderBy('id')->get()
                ->map(fn (SharedReport $share): array => ['created_at' => $share->created_at->toIso8601String(), 'expires_at' => $share->expires_at->toIso8601String(), 'views' => $share->views])->all(),
            'company_notes' => CompanyNote::query()->where('user_id', $userId)->orderBy('id')->get()
                ->map(fn (CompanyNote $note): array => ['company' => $note->company, 'fact' => $note->fact, 'source_url' => $note->source_url, 'origin' => $note->origin->value, 'approved' => $note->approved])
                ->all(),
            'viewed_jobs' => DB::table('job_views')->where('user_id', $userId)->count(),
            'prepared_applications' => ApplicationPackage::query()->with('job:id,title,company')->where('user_id', $userId)->orderBy('id')->get()
                ->map(fn (ApplicationPackage $package): array => [
                    'job' => $package->job !== null ? "{$package->job->title} · {$package->job->company}" : null,
                    'status' => $package->status->value,
                    'language' => $package->language,
                    'cover_letter' => $package->cover_letter,
                    'resume' => $package->resume,
                    'created_at' => $package->created_at->toIso8601String(),
                    'approved_at' => $package->approved_at?->toIso8601String(),
                ])->values()->all(),
        ]);
    }

    public function erase(int $userId): void
    {
        // applications (with their events) and job_views cascade from users.
    }
}
