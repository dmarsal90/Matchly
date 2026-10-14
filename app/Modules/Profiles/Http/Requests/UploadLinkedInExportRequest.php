<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

final class UploadLinkedInExportRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Extension and server-detected MIME type; the archive itself is vetted when read.
            'file' => [
                'required',
                'file',
                'max:'.(int) config('linkedin.max_upload_kilobytes'),
                'extensions:zip',
                'mimetypes:application/zip,application/x-zip-compressed',
            ],
            'consent' => ['accepted'],
        ];
    }

    public function archive(): UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : throw new LogicException('Validated request has no file.');
    }
}
