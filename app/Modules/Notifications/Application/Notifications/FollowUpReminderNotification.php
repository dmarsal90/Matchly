<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Notifications;

use App\Modules\Applications\Application\Generators\FollowUpDraftWriter;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An application sent a while ago still has no answer. Stored for the in-app
 * notification centre, pushed live when real-time is on, and e-mailed with a
 * draft message when the user consented to e-mail. The draft is built when
 * the mail is rendered and never stored with the notification.
 */
final class FollowUpReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $applicationId, public readonly int $days) {}

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
        $application = $this->application();
        $job = $application?->job;

        return (new MailMessage)
            ->subject(__('follow_up.mail.subject', ['company' => $job?->company]))
            ->markdown('mail.applications.follow-up', [
                'company' => $job?->company,
                'title' => $job?->title,
                'date' => $application?->applied_at?->translatedFormat('j F Y'),
                'draft' => $application === null ? '' : app(FollowUpDraftWriter::class)->write($application),
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
            'type' => 'follow_up',
            'application_id' => $this->applicationId,
            'title' => $job?->title,
            'company' => $job?->company,
            'days' => $this->days,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'application.follow_up';
    }

    private function application(): ?Application
    {
        return Application::query()->with('job', 'user')->find($this->applicationId);
    }
}
