<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\Exceptions;

use RuntimeException;

/**
 * No provider could answer. The reason is a stable code use cases and the UI
 * translate (ai.errors.<reason>).
 */
final class AiUnavailable extends RuntimeException
{
    public const string DISABLED = 'disabled';

    public const string NO_PROVIDER = 'no_provider';

    public const string BUDGET_EXCEEDED = 'budget_exceeded';

    public const string RATE_LIMITED = 'rate_limited';

    public const string FAILED = 'failed';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
