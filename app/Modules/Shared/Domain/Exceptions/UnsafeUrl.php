<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

use RuntimeException;

final class UnsafeUrl extends RuntimeException
{
    public static function because(string $url, string $reason): self
    {
        return new self("Refusing to request [{$url}]: {$reason}");
    }
}
