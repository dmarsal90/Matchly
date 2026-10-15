<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/** The in-app notification centre: list, latest (for the header), mark as read. */
final class NotificationController
{
    private const int PER_PAGE = 20;

    public function index(#[CurrentUser] User $user): Response
    {
        $page = $user->notifications()->latest()->paginate(self::PER_PAGE);

        return Inertia::render('notifications/Index', [
            'notifications' => [
                'data' => array_map($this->present(...), $page->items()),
                'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            ],
        ]);
    }

    /** Latest notifications for the header dropdown. */
    public function latest(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->limit(8)->get()->map($this->present(...))->values(),
        ]);
    }

    public function read(string $notification, #[CurrentUser] User $user): RedirectResponse|JsonResponse
    {
        // Scoped to the user's own notifications: someone else's id is simply not found.
        $user->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return request()->wantsJson() && ! request()->header('X-Inertia') ? response()->json(['ok' => true]) : back();
    }

    public function readAll(#[CurrentUser] User $user): RedirectResponse|JsonResponse
    {
        $user->unreadNotifications()->update(['read_at' => now()]);

        return request()->wantsJson() && ! request()->header('X-Inertia') ? response()->json(['ok' => true]) : back();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
