<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Contracts;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Providers\Domain\Data\RawJob;

interface JobNormalizer
{
    /**
     * Maps a source payload to the common draft, or null to skip it
     * (incomplete, not a job, explicitly on-site...).
     */
    public function normalize(RawJob $raw): ?JobDraft;
}
