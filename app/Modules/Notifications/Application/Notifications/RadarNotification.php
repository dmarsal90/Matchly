<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Notifications;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A company the person follows has new open jobs. Stored for the in-app
 * notification centre, pushed live when real-time is on, and e-mailed only
 * when the user consented to e-mail.
 */
final class RadarNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<array{id: int, title: string}>  $jobs  the first few of the new ones
     */
    public function __construct(public readonly string $company, public readonly int $count, public readonly array $jobs) {}

    /**
     * @return list<int> seconds between retries
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['mail' => 'notifications', 'database' => 'notifications', 'broadcast' => 'notifications'];
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return array_values(array_filter([
            'database',
            $notifiable->wantsRealtimeNotifications() ? 'broadcast' : null,
            $notifiable->acceptsEmailAlerts() ? 'mail' : null,
        ]));
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(trans_choice('radar.mail.subject', $this->count, ['company' => $this->company, 'count' => $this->count]))
            ->markdown('mail.alerts.radar', [
                'company' => $this->company,
                'count' => $this->count,
                'jobs' => array_map(fn (array $job): array => ['title' => $job['title'], 'url' => route('jobs.show', ['job' => $job['id']])], $this->jobs),
                'manageUrl' => route('radar.index'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return ['type' => 'radar', 'company' => $this->company, 'count' => $this->count, 'jobs' => $this->jobs];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'company.radar';
    }
}
