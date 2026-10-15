<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Providers\Infrastructure\Models\AtsCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AtsCompany>
 */
final class AtsCompanyFactory extends Factory
{
    protected $model = AtsCompany::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'platform' => fake()->randomElement(AtsCompany::PLATFORMS),
            'identifier' => str($name)->slug()->toString(),
            'name' => $name,
            'enabled' => true,
        ];
    }

    public function platform(string $platform): self
    {
        return $this->state(['platform' => $platform]);
    }
}
