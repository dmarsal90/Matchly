<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AtsCompanyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $platform
 * @property string $identifier
 * @property string $name
 * @property string|null $website_url
 * @property bool $enabled
 * @property int $last_job_count
 * @property CarbonImmutable|null $last_fetched_at
 * @property string|null $last_error
 */
#[UseFactory(AtsCompanyFactory::class)]
final class AtsCompany extends Model
{
    /** @use HasFactory<AtsCompanyFactory> */
    use HasFactory;

    public const array PLATFORMS = ['greenhouse', 'lever', 'ashby', 'workable', 'smartrecruiters'];

    protected $fillable = ['platform', 'identifier', 'name', 'website_url', 'enabled'];

    protected $attributes = [
        'enabled' => true,
        'last_job_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_job_count' => 'integer',
            'last_fetched_at' => 'immutable_datetime',
        ];
    }
}
