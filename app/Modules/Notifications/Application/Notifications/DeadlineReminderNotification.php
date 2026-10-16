<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Notifications;

use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A deadline the person set on an application is close. Stored for the in-app
 * notification centre, pushed live when real-time is on, and e-mailed only
 * when the user consented to e-mail.
 */
final class DeadlineReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param  string  $deadline  Y-m-d */
    public function __construct(public readonly int $applicationId, public readonly string $deadline, public readonly int $daysLeft) {}

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
        if ($this->application() === null) {
            return [];
        }

        return array_values(array_filter([
            'database',
            $notifiable->wantsRealtimeNotifications() ? 'broadcast' : null,
            $notifiable->acceptsEmailAlerts() ? 'mail' : null,
        ]));
    }

    public function toMail(User $notifiable): MailMessage
    {
        $job = $this->application()?->job;

        return (new MailMessage)
            ->subject(__('deadline.mail.subject', ['company' => $job?->company]))
            ->markdown('mail.applications.deadline', [
                'company' => $job?->company,
                'title' => $job?->title,
                'date' => CarbonImmutable::parse($this->deadline)->settings(['locale' => app()->getLocale()])->isoFormat('LL'),
                'url' => route('applications.index'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $job = $this->application()?->job;

        return [
            'type' => 'deadline',
            'application_id' => $this->applicationId,
            'title' => $job?->title,
            'company' => $job?->company,
            'deadline' => $this->deadline,
            'days_left' => $this->daysLeft,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'application.deadline';
    }

    private function application(): ?Application
    {
        return Application::query()->with('job')->find($this->applicationId);
    }
}
