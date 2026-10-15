<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Resources;

use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Status of an import, without its contents (shown on the skills page).
 *
 * @mixin ProfileImport
 */
final class ProfileImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = $this->payload ?? [];

        return [
            'id' => $this->id,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'reference' => $this->reference,
            'error' => $this->error,
            'skills_count' => count((array) ($payload['skills'] ?? [])),
            'created_at' => $this->created_at->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
        ];
    }
}
