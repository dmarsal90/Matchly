<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Resources;

use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CvDocument
 */
final class CvDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'size' => $this->size,
            'type' => $this->mime_type === CvDocument::PDF ? 'pdf' : 'docx',
            'status' => $this->status->value,
            'is_primary' => $this->is_primary,
            'error' => $this->error,
            'uploaded_at' => $this->created_at->toIso8601String(),
        ];
    }
}
