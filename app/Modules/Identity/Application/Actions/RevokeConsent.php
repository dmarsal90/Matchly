<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Events\ConsentRevoked;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class RevokeConsent
{
    public function __construct(private Dispatcher $events) {}

    public function handle(User $user, ConsentPurpose $purpose): void
    {
        $revoked = $user->consents()
            ->where('purpose', $purpose)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => CarbonImmutable::now()]);

        if ($revoked > 0) {
            $this->events->dispatch(new ConsentRevoked($user->id, $purpose));
        }
    }
}
