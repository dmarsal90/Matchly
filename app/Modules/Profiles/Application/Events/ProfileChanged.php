<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Events;

/** Profile data used for matching changed; scores must be recomputed. */
final readonly class ProfileChanged
{
    public function __construct(public int $userId) {}
}
