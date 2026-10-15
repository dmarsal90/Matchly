<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Application\Actions\UpdateProfile;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Profiles\Http\Requests\UpdateProfileRequest;
use App\Modules\Profiles\Http\Resources\CvDocumentResource;
use App\Modules\Shared\Infrastructure\Localization\Countries;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        $profile = $user->profile;

        return Inertia::render('profile/Edit', [
            'profile' => [
                'headline' => $profile?->headline,
                'phone' => $profile?->phone,
                'country' => $profile?->country,
                'city' => $profile?->city,
                'github_username' => $profile?->github_username,
                'linkedin_url' => $profile?->linkedin_url,
                'portfolio_url' => $profile?->portfolio_url,
                'languages' => $profile->languages ?? [],
                'salary_min' => $profile?->salary_min,
                'salary_currency' => $profile?->salary_currency,
                'salary_period' => $profile?->salary_period?->value,
                'work_modes' => $profile->preferences['work_modes'] ?? [],
                'employment_types' => $profile->preferences['employment_types'] ?? [],
            ],
            'cvDocuments' => CvDocumentResource::collection($user->cvDocuments()->latest('id')->get())->resolve(),
            'options' => [
                'countries' => Countries::names(app()->getLocale()),
                'currencies' => UpdateProfileRequest::CURRENCIES,
                'languageLevels' => array_column(LanguageLevel::cases(), 'value'),
                'salaryPeriods' => array_column(SalaryPeriod::cases(), 'value'),
                'workModes' => array_column(WorkMode::cases(), 'value'),
                'employmentTypes' => array_column(EmploymentType::cases(), 'value'),
                'maxCvKilobytes' => (int) config('privacy.cv.max_kilobytes'),
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request, #[CurrentUser] User $user, UpdateProfile $update): RedirectResponse
    {
        $update->handle($user, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('profile.saved')]);

        return to_route('career.edit');
    }
}
