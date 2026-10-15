<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Infrastructure\Console;

use App\Modules\Alerts\Application\Actions\SendAlertDigests;
use App\Modules\Alerts\Domain\Enums\AlertFrequency;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alerts:digest {frequency : daily or weekly}')]
#[Description('Send the daily or weekly digests of saved-search alerts')]
final class SendDigestsCommand extends Command
{
    public function handle(SendAlertDigests $digests): int
    {
        $frequency = AlertFrequency::tryFrom((string) $this->argument('frequency'));
        if ($frequency === null || ! $frequency->isDigest()) {
            $this->components->error('Frequency must be "daily" or "weekly".');

            return self::FAILURE;
        }

        $this->components->info("Sent {$digests->handle($frequency)} {$frequency->value} digests.");

        return self::SUCCESS;
    }
}
