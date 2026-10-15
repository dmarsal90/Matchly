<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Exceptions;

use RuntimeException;
use Throwable;

final class ProviderRequestFailed extends RuntimeException
{
    public static function for(string $provider, string $url, ?int $status, ?Throwable $previous = null): self
    {
        $suffix = $status !== null ? "HTTP {$status}" : ($previous?->getMessage() ?? 'unknown error');

        return new self("[{$provider}] request to {$url} failed: {$suffix}", $status ?? 0, $previous);
    }
}
