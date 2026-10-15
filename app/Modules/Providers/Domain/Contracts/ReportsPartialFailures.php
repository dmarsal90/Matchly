<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Contracts;

/**
 * Providers that keep going when part of a fetch fails. A run with failures
 * never closes "missing" jobs, because they may just be unreachable.
 */
interface ReportsPartialFailures
{
    /**
     * @return list<string>
     */
    public function failures(): array;
}
