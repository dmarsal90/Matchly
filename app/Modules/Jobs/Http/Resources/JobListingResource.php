<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Http\Resources;

use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin JobListing
 */
final class JobListingResource extends JsonResource
{
    /** @var array<int, string> job id => application status of the viewer */
    private array $tracked = [];

    /** @var array<int, true> */
    private array $viewed = [];

    private bool $withDescription = false;

    /**
     * @param  array<int, string>  $tracked
     * @param  array<int, true>  $viewed
     */
    public function withViewerState(array $tracked, array $viewed): self
    {
        $this->tracked = $tracked;
        $this->viewed = $viewed;

        return $this;
    }

    public function withDescription(): self
    {
        $this->withDescription = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => Str::slug(Str::limit($this->title.' '.$this->company, 80, '')),
            'title' => $this->title,
            'company' => $this->company,
            'company_logo_url' => $this->company_logo_url,
            'location' => $this->location,
            'work_mode' => $this->work_mode->value,
            'remote_scope' => $this->remote_scope?->value,
            'allowed_regions' => $this->allowed_regions,
            'employment_type' => $this->employment_type?->value,
            'seniority' => $this->seniority?->value,
            'category' => $this->category?->value,
            'role' => $this->role?->value,
            'salary' => $this->salary_min !== null || $this->salary_max !== null || $this->salary_text !== null ? [
                'min' => $this->salary_min !== null ? (float) $this->salary_min : null,
                'max' => $this->salary_max !== null ? (float) $this->salary_max : null,
                'currency' => $this->salary_currency,
                'period' => $this->salary_period?->value,
                'text' => $this->salary_text,
            ] : null,
            'tags' => $this->withDescription ? $this->tags : array_slice($this->tags, 0, 6),
            'posted_at' => $this->sort_at->toIso8601String(),
            'status' => $this->status->value,
            'apply_url' => $this->apply_url,
            // Attribution required by most free job APIs: credit and link back to the source.
            'source' => [
                'key' => $this->source->key,
                'name' => $this->source->name,
                'url' => $this->source_url ?? $this->source->homepage_url,
            ],
            'tracking_status' => $this->tracked[$this->id] ?? null,
            'viewed' => isset($this->viewed[$this->id]),
            'description' => $this->when($this->withDescription, fn (): string => $this->description),
        ];
    }
}
