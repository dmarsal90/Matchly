<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Models;

use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $homepage_url
 * @property string|null $terms_url
 * @property bool $requires_attribution
 * @property bool $enabled
 * @property CarbonImmutable|null $last_fetched_at
 * @property CarbonImmutable|null $last_succeeded_at
 * @property int $consecutive_failures
 * @property string|null $last_error
 */
final class Source extends Model
{
    protected $fillable = [
        'key', 'name', 'homepage_url', 'terms_url', 'requires_attribution', 'enabled',
        'last_fetched_at', 'last_succeeded_at', 'consecutive_failures', 'last_error',
    ];

    protected $attributes = [
        'enabled' => true,
        'consecutive_failures' => 0,
    ];

    /**
     * Finds the row for a provider, creating it (and refreshing its public
     * metadata) from the code-level definition.
     */
    public static function forDefinition(ProviderDefinition $definition): self
    {
        $source = self::query()->firstOrNew(['key' => $definition->key]);
        $source->fill([
            'name' => $definition->name,
            'homepage_url' => $definition->homepageUrl,
            'terms_url' => $definition->termsUrl,
            'requires_attribution' => $definition->requiresAttribution,
        ]);
        $source->save();

        return $source;
    }

    /**
     * @return HasMany<JobListing, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(JobListing::class);
    }

    /**
     * @return HasMany<IngestionRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(IngestionRun::class);
    }

    protected function casts(): array
    {
        return [
            'requires_attribution' => 'boolean',
            'enabled' => 'boolean',
            'last_fetched_at' => 'immutable_datetime',
            'last_succeeded_at' => 'immutable_datetime',
            'consecutive_failures' => 'integer',
        ];
    }
}
