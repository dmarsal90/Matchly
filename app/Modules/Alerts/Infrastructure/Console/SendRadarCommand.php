<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Infrastructure\Console;

use App\Modules\Alerts\Application\Actions\SendRadarNotifications;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alerts:radar')]
#[Description('Notify people about new jobs from the companies they follow')]
final class SendRadarCommand extends Command
{
    public function handle(SendRadarNotifications $radar): int
    {
        $this->components->info("Sent {$radar->handle()} radar notifications.");

        return self::SUCCESS;
    }
}
