<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Data;

use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;

final readonly class Classification
{
    /**
     * @param  list<string>  $allowedRegions  ISO 3166-1 alpha-2 codes and/or Region values
     */
    public function __construct(
        public WorkMode $workMode,
        public ?RemoteScope $remoteScope,
        public array $allowedRegions,
        public ?JobCategory $category,
        public ?JobRole $role,
        public ?Seniority $seniority,
    ) {}
}
