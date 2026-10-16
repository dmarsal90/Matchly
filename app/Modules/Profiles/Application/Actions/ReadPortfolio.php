<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Exceptions\ImportFailed;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use App\Modules\Profiles\Infrastructure\Portfolio\PortfolioReader;
use Psr\Log\LoggerInterface;
use Throwable;

/** Runs a queued portfolio import and leaves it ready for review, or failed with a reason the person can understand. */
final readonly class ReadPortfolio
{
    public function __construct(private PortfolioReader $reader, private LoggerInterface $logger) {}

    public function handle(ProfileImport $import): void
    {
        if ($import->status !== ImportStatus::Processing) {
            return;
        }

        $user = $import->user;
        // Consent may have been withdrawn while the job waited in the queue.
        if ($user === null || ! $user->hasConsented(ConsentPurpose::PortfolioImport)) {
            $import->delete();

            return;
        }

        try {
            $payload = $this->reader->read((string) $import->reference);
            $import->fill(['status' => ImportStatus::PendingReview, 'payload' => $payload, 'error' => null])->save();
        } catch (ImportFailed $exception) {
            $import->fill(['status' => ImportStatus::Failed, 'error' => $exception->reason])->save();
        } catch (Throwable $exception) {
            $this->logger->error('Portfolio import failed', ['import' => $import->id, 'exception' => $exception]);
            $import->fill(['status' => ImportStatus::Failed, 'error' => ImportFailed::UNAVAILABLE])->save();
        }
    }
}
