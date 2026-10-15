<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Domain\Enums\CvStatus;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Profiles\Infrastructure\Queue\ExtractCvTextJob;
use App\Modules\Shared\Infrastructure\Files\EncryptedFileStore;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Validation\ValidationException;

final readonly class UploadCv
{
    /** Magic bytes per accepted type: the client-sent MIME type is never trusted alone. */
    private const array SIGNATURES = [
        CvDocument::PDF => '%PDF-',
        CvDocument::DOCX => "PK\x03\x04",
    ];

    public function __construct(
        private EncryptedFileStore $files,
        private GrantConsent $grantConsent,
        private Config $config,
    ) {}

    public function handle(User $user, string $contents, string $originalName, string $mimeType): CvDocument
    {
        $signature = self::SIGNATURES[$mimeType] ?? null;
        if ($signature === null || ! str_starts_with($contents, $signature)) {
            throw ValidationException::withMessages(['file' => __('profile.cv.invalid_file')]);
        }

        if ($user->cvDocuments()->count() >= (int) $this->config->get('privacy.cv.max_documents', 5)) {
            throw ValidationException::withMessages(['file' => __('profile.cv.too_many', ['max' => $this->config->get('privacy.cv.max_documents')])]);
        }

        // Uploading comes with an explicit consent checkbox; record it before processing.
        $this->grantConsent->handle($user, ConsentPurpose::CvProcessing);

        $document = new CvDocument([
            'disk' => $this->files->disk(),
            'path' => $this->files->put("users/{$user->id}/cv", $contents),
            'original_name' => mb_substr(basename($originalName), 0, 255),
            'mime_type' => $mimeType,
            'size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'is_primary' => ! $user->cvDocuments()->where('is_primary', true)->exists(),
            'status' => CvStatus::Uploaded,
        ]);
        $document->user()->associate($user);
        $document->save();

        ExtractCvTextJob::dispatch($document->id);

        return $document;
    }
}
