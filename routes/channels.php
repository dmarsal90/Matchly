<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Private per-user channel for notifications (User::receivesBroadcastNotificationsOn()).
Broadcast::channel('users.{id}', fn (User $user, int $id): bool => $user->id === $id);
