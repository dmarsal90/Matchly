<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Domain\Contracts\CvTextExtractor;
use App\Modules\Profiles\Domain\Enums\CvStatus;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Shared\Infrastructure\Files\EncryptedFileStore;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Turns the uploaded file into plain text. Structuring it (experience,
 * education, skills) with AI happens in a later step the user reviews.
 */
final readonly class ExtractCvText
{
    /**
     * @param  iterable<CvTextExtractor>  $extractors
     */
    public function __construct(
        private iterable $extractors,
        private EncryptedFileStore $files,
        private Dispatcher $events,
    ) {}

    public function handle(CvDocument $document): void
    {
        try {
            $extractor = $this->extractorFor($document->mime_type);
            $text = $extractor->extract($this->files->get($document->path));

            if ($text === '') {
                throw new RuntimeException('No selectable text found (scanned images are not supported).');
            }

            $document->forceFill([
                'extracted_text' => $text,
                'status' => CvStatus::Extracted,
                'extracted_at' => CarbonImmutable::now(),
                'error' => null,
            ])->save();

            $this->events->dispatch(new ProfileChanged($document->user_id));
        } catch (Throwable $exception) {
            $document->forceFill([
                'status' => CvStatus::Failed,
                'error' => Str::limit($exception->getMessage(), 500),
            ])->save();
        }
    }

    private function extractorFor(string $mimeType): CvTextExtractor
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mimeType)) {
                return $extractor;
            }
        }

        throw new RuntimeException("Unsupported CV type {$mimeType}.");
    }
}
