<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Requests;

use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

final class UploadCvRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('privacy.cv.max_kilobytes'),
                'mimes:pdf,docx',
                'mimetypes:'.CvDocument::PDF.','.CvDocument::DOCX,
            ],
            // Explicit, specific consent to process the CV (GDPR art. 7).
            'consent' => ['accepted'],
        ];
    }

    public function cv(): UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : throw new LogicException('Validated request has no file.');
    }

    /** MIME type detected from the content by the server, not the one the browser sent. */
    public function detectedMimeType(): string
    {
        return (string) $this->cv()->getMimeType();
    }
}
