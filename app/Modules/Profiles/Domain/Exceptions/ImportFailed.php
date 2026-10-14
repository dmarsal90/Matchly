<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Exceptions;

use RuntimeException;
use Throwable;

/**
 * An import that cannot go on. The reason is a stable code the UI
 * translates (skills.errors.<reason>); details stay in the logs.
 */
final class ImportFailed extends RuntimeException
{
    public const string NOT_FOUND = 'not_found';

    public const string RATE_LIMITED = 'rate_limited';

    public const string UNAVAILABLE = 'unavailable';

    public const string NO_REPOSITORIES = 'no_repositories';

    public const string INVALID_ARCHIVE = 'invalid_archive';

    public const string UNSAFE_ARCHIVE = 'unsafe_archive';

    public const string NOT_A_LINKEDIN_EXPORT = 'not_a_linkedin_export';

    public const string NO_CV_TEXT = 'no_cv_text';

    public const string NOTHING_FOUND = 'nothing_found';

    private function __construct(public readonly string $reason, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }

    public static function because(string $reason, string $message = '', ?Throwable $previous = null): self
    {
        return new self($reason, $message !== '' ? $message : $reason, $previous);
    }
}
