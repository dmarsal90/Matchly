<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Contracts;

use App\Modules\Jobs\Domain\Data\Classification;
use App\Modules\Jobs\Domain\Data\JobDraft;

/**
 * Decides work mode, remote scope, allowed regions and taxonomy for a draft.
 * The default implementation is rule-based; an LLM-backed one can replace it
 * through the container without touching the ingestion pipeline.
 */
interface WorkModeClassifier
{
    public function classify(JobDraft $draft): Classification;
}
