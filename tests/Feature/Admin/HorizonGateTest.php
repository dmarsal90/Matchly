<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Gate;

it('opens Horizon and Pulse only to verified admins or verified allowlisted e-mails', function (): void {
    config(['horizon.admin_emails' => ['ops@example.com', 'dev@example.com']]);

    $verifiedAdmin = User::factory()->create(['is_admin' => true]);
    $unverifiedAdmin = User::factory()->unverified()->create(['is_admin' => true]);
    $verifiedListed = User::factory()->create(['email' => 'ops@example.com']);
    $unverifiedListed = User::factory()->unverified()->create(['email' => 'dev@example.com']);
    $stranger = User::factory()->create();

    foreach (['viewHorizon', 'viewPulse'] as $ability) {
        expect(Gate::forUser($verifiedAdmin)->allows($ability))->toBeTrue()
            ->and(Gate::forUser($unverifiedAdmin)->allows($ability))->toBeFalse()
            ->and(Gate::forUser($stranger)->allows($ability))->toBeFalse();
    }
    expect(Gate::forUser($verifiedListed)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($unverifiedListed)->allows('viewHorizon'))->toBeFalse();
});
