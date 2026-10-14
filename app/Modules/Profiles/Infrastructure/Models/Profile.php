<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $phone
 * @property string|null $country
 * @property string|null $city
 * @property string|null $github_username
 * @property string|null $linkedin_url
 * @property string|null $portfolio_url
 * @property list<array{code: string, level: string}> $languages
 * @property int|null $salary_min
 * @property string|null $salary_currency
 * @property SalaryPeriod|null $salary_period
 * @property array<string, mixed> $preferences
 */
final class Profile extends Model
{
    protected $fillable = [
        'headline', 'phone', 'country', 'city', 'github_username', 'linkedin_url', 'portfolio_url',
        'languages', 'salary_min', 'salary_currency', 'salary_period', 'preferences',
    ];

    protected $attributes = [
        'languages' => '[]',
        'preferences' => '{}',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'languages' => 'array',
            'preferences' => 'array',
            'salary_min' => 'integer',
            'salary_period' => SalaryPeriod::class,
        ];
    }
}
