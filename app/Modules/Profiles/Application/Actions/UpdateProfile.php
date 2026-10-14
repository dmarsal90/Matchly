<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Domain\Data\ProfileData;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Profiles\Infrastructure\Models\Profile;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class UpdateProfile
{
    public function __construct(private Dispatcher $events) {}

    public function handle(User $user, ProfileData $data): Profile
    {
        $profile = $user->profile()->firstOrNew();

        $profile->fill([
            'headline' => $data->headline,
            'phone' => $data->phone,
            'country' => $data->country !== null ? strtoupper($data->country) : null,
            'city' => $data->city,
            'github_username' => $data->githubUsername,
            'linkedin_url' => $data->linkedinUrl,
            'portfolio_url' => $data->portfolioUrl,
            'languages' => array_map(
                fn (string $code, LanguageLevel $level): array => ['code' => strtolower($code), 'level' => $level->value],
                array_keys($data->languages),
                array_values($data->languages),
            ),
            'salary_min' => $data->salaryMin,
            'salary_currency' => $data->salaryCurrency !== null ? strtoupper($data->salaryCurrency) : null,
            'salary_period' => $data->salaryPeriod,
            'preferences' => [
                ...$profile->preferences ?? [],
                'work_modes' => array_map(fn ($mode) => $mode->value, $data->workModes),
                'employment_types' => array_map(fn ($type) => $type->value, $data->employmentTypes),
            ],
        ]);

        $dirty = $profile->isDirty();
        $profile->save();

        if ($dirty) {
            $this->events->dispatch(new ProfileChanged($user->id));
        }

        return $profile;
    }
}
