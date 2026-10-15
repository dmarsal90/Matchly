<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Contracts;

use App\Modules\Providers\Domain\Data\AtsCompanyRef;

/** Companies whose public ATS boards are polled, managed from the admin panel. */
interface AtsCompanyDirectory
{
    /**
     * @return list<AtsCompanyRef>
     */
    public function enabledFor(string $platform): array;

    public function markSucceeded(int $companyId, int $jobCount): void;

    public function markFailed(int $companyId, string $error): void;
}
