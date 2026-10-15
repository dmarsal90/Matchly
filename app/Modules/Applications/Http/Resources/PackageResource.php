<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Resources;

use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full package for its owner's review page.
 *
 * @mixin ApplicationPackage
 */
final class PackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'language' => $this->language,
            'tone' => $this->tone->value,
            'length' => $this->length->value,
            'cover_letter' => $this->cover_letter,
            'resume' => $this->resume,
            'master_resume' => $this->master_resume,
            'letter_source' => $this->letter_source?->value,
            'resume_source' => $this->resume_source?->value,
            'warnings' => $this->warnings ?? [],
            'error' => $this->error,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
