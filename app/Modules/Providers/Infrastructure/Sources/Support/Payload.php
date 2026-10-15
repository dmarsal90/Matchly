<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Support;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Typed, forgiving accessors over a decoded API payload (dot notation for
 * nested keys). Sources are inconsistent: numbers arrive as strings, dates as
 * epochs, milliseconds or ISO strings, lists as comma-separated text.
 */
final readonly class Payload
{
    /**
     * @param  array<mixed>  $data
     */
    public function __construct(private array $data) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function of(array $data): self
    {
        return new self($data);
    }

    public function get(string $key): mixed
    {
        $value = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key): ?string
    {
        $value = $this->get($key);

        if (is_string($value) || is_int($value) || is_float($value)) {
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        }

        return null;
    }

    public function float(string $key): ?float
    {
        $value = $this->get($key);

        return is_numeric($value) ? (float) $value : null;
    }

    public function bool(string $key): ?bool
    {
        $value = $this->get($key);

        return match (true) {
            is_bool($value) => $value,
            $value === 1, $value === '1', $value === 'true' => true,
            $value === 0, $value === '0', $value === 'false' => false,
            default => null,
        };
    }

    /**
     * @return array<mixed>
     */
    public function array(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? $value : [];
    }

    /**
     * Strings from a list, or from comma/semicolon separated text.
     *
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->get($key);

        if (is_string($value)) {
            $value = preg_split('/\s*[,;]\s*/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if ((is_string($item) || is_int($item)) && trim((string) $item) !== '') {
                $strings[] = trim((string) $item);
            }
        }

        return array_values(array_unique($strings));
    }

    public function date(string $key): ?DateTimeImmutable
    {
        $value = $this->get($key);

        try {
            return match (true) {
                is_int($value) || (is_string($value) && ctype_digit($value)) => self::fromEpoch((int) $value),
                is_float($value) => self::fromEpoch((int) $value),
                is_string($value) && trim($value) !== '' => new DateTimeImmutable($value, new DateTimeZone('UTC')),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    private static function fromEpoch(int $value): DateTimeImmutable
    {
        // Lever and others send milliseconds.
        if ($value > 100_000_000_000) {
            $value = intdiv($value, 1000);
        }

        return (new DateTimeImmutable('@'.$value))->setTimezone(new DateTimeZone('UTC'));
    }
}
