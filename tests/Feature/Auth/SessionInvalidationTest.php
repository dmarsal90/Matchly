<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Hash;

it('ends a session whose password was changed elsewhere', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('profile.edit'))->assertOk();

    $user->forceFill(['password' => Hash::make('changed-by-reset-1A!')])->save();

    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

it('keeps the session that changes the password', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('profile.edit'))->assertOk();

    $this->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-Password-123!',
        'password_confirmation' => 'new-Password-123!',
    ])->assertSessionHasNoErrors();

    $this->get(route('profile.edit'))->assertOk();
});
