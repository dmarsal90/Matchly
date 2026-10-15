<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;

final readonly class GrantConsent
{
    public function __construct(private Config $config) {}

    public function handle(User $user, ConsentPurpose $purpose): void
    {
        $version = (string) $this->config->get('privacy.policy_version');

        $current = $user->consents()->where('purpose', $purpose)->whereNull('revoked_at')->first();
        if ($current !== null && $current->policy_version === $version) {
            return;
        }

        // A consent given under an older policy is superseded, not stacked.
        $current?->forceFill(['revoked_at' => CarbonImmutable::now()])->save();

        $user->consents()->create([
            'purpose' => $purpose,
            'policy_version' => $version,
            'granted_at' => CarbonImmutable::now(),
        ]);
    }
}
