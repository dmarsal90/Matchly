<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\PackageStatus;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** The person reviewed the package: it can now be exported and used. */
final readonly class ApprovePackage
{
    public function handle(ApplicationPackage $package): void
    {
        if ($package->status !== PackageStatus::Draft) {
            throw ValidationException::withMessages(['package' => __('quick_apply.not_approvable')]);
        }

        $package->fill(['status' => PackageStatus::Approved, 'approved_at' => CarbonImmutable::now()])->save();
    }
}
