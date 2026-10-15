<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase wipes whatever database it is pointed at: refuse to run
        // if the environment leaked in (e.g. Docker-injected variables).
        if (! $this->app->environment('testing')) {
            throw new RuntimeException('Tests must run with APP_ENV=testing; check phpunit.xml <server> overrides.');
        }

        // Feature tests assert on responses, not on built assets: no Vite manifest needed.
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
