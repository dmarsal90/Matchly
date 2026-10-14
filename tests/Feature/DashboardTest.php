<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;

it('sends guests to the login page', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('uses the job listing as home for signed-in users', function (): void {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertRedirect('/jobs');
});
