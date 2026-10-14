<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Data;

use InvalidArgumentException;

/** A GitHub login, accepted as "octocat", "@octocat" or a profile URL. */
final readonly class GitHubUsername
{
    public const string PATTERN = '/^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/';

    private function __construct(public string $value) {}

    public static function parse(string $input): self
    {
        $value = trim($input);
        $value = (string) preg_replace('#^(?:https?://)?(?:www\.)?github\.com/#i', '', $value);
        $value = ltrim(explode('/', explode('?', $value)[0])[0], '@');

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Not a GitHub username.');
        }

        return new self($value);
    }

    public static function tryParse(?string $input): ?self
    {
        if ($input === null) {
            return null;
        }

        try {
            return self::parse($input);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
