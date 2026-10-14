<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Architecture');

/**
 * Decoded provider fixture (real API responses, trimmed) from tests/Fixtures/providers.
 *
 * @return array<mixed>
 */
function providerFixture(string $name): array
{
    return json_decode(providerFixtureRaw($name), true, flags: JSON_THROW_ON_ERROR);
}

function providerFixtureRaw(string $name): string
{
    return (string) file_get_contents(__DIR__."/Fixtures/providers/{$name}");
}
