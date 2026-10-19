<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Services\GhostJobSignals;

it('says nothing about a fresh posting or one mild signal', function (): void {
    expect(GhostJobSignals::assess(3, 0))->toBeNull()
        ->and(GhostJobSignals::assess(25, 0))->toBeNull()
        ->and(GhostJobSignals::assess(3, 1))->toBeNull();
});

it('warns about a very old posting or a repeatedly reposted one', function (): void {
    expect(GhostJobSignals::assess(70, 0))->toBe(['level' => 'medium', 'signals' => ['very_old']])
        ->and(GhostJobSignals::assess(3, 2))->toBe(['level' => 'medium', 'signals' => ['reposted']]);
});

it('rates old and reposted together as high', function (): void {
    expect(GhostJobSignals::assess(25, 3))->toBe(['level' => 'high', 'signals' => ['old', 'reposted']]);
});
