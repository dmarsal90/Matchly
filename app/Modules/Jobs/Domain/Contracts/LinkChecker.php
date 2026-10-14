<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Contracts;

interface LinkChecker
{
    /**
     * True when the URL clearly points to a removed or closed posting
     * (404/410, ATS "job not found" redirects). Network errors return false:
     * a flaky host must never close a job.
     */
    public function isDead(string $url): bool;
}
