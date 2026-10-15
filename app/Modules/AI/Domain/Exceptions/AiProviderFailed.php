<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\Exceptions;

use RuntimeException;
use Throwable;

final class AiProviderFailed extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /** Rate limits, overload and server errors are worth retrying; the rest is not. */
    public static function fromStatus(string $provider, int $status, string $body): self
    {
        return new self(
            sprintf('%s answered %d: %s', $provider, $status, mb_substr($body, 0, 200)),
            retryable: in_array($status, [408, 409, 425, 429, 500, 502, 503, 504, 529], true),
        );
    }
}
