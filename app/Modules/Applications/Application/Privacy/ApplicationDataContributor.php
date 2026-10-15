<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Privacy;

use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Identity\Domain\Contracts\PersonalDataContributor;
use App\Modules\Identity\Domain\Data\PersonalData;
use Illuminate\Support\Facades\DB;

final class ApplicationDataContributor implements PersonalDataContributor
{
    public function export(int $userId): PersonalData
    {
        $applications = Application::query()->with('job:id,title,company,apply_url')->where('user_id', $userId)->orderBy('id')->get();

        return new PersonalData('applications', [
            'applications' => $applications->map(fn (Application $application): array => [
                'job' => ['title' => $application->job->title, 'company' => $application->job->company, 'url' => $application->job->apply_url],
                'status' => $application->status->value,
                'notes' => $application->notes,
                'applied_at' => $application->applied_at?->toIso8601String(),
                'created_at' => $application->created_at->toIso8601String(),
            ])->values()->all(),
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
        // applications and job_views cascade from users.
    }
}
