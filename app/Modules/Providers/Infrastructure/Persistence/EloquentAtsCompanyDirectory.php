<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Persistence;

use App\Modules\Providers\Domain\Contracts\AtsCompanyDirectory;
use App\Modules\Providers\Domain\Data\AtsCompanyRef;
use App\Modules\Providers\Infrastructure\Models\AtsCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class EloquentAtsCompanyDirectory implements AtsCompanyDirectory
{
    public function enabledFor(string $platform): array
    {
        return array_values(AtsCompany::query()
            ->where('platform', $platform)
            ->where('enabled', true)
            ->orderBy('identifier')
            ->get()
            ->map(fn (AtsCompany $company): AtsCompanyRef => new AtsCompanyRef($company->id, $company->identifier, $company->name))
            ->all());
    }

    public function markSucceeded(int $companyId, int $jobCount): void
    {
        AtsCompany::query()->whereKey($companyId)->update([
            'last_fetched_at' => CarbonImmutable::now(),
            'last_job_count' => $jobCount,
            'last_error' => null,
        ]);
    }

    public function markFailed(int $companyId, string $error): void
    {
        AtsCompany::query()->whereKey($companyId)->update([
            'last_fetched_at' => CarbonImmutable::now(),
            'last_error' => Str::limit($error, 2000),
        ]);
    }
}
