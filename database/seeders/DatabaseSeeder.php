<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(AtsCompanySeeder::class);

        if (app()->environment('local') && ! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
        }
    }
}
