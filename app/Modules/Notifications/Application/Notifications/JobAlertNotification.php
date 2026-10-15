<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Notifications;

use App\Modules\Alerts\Domain\Enums\AlertFrequency;
use App\Modules\Alerts\Infrastructure\Models\SavedSearch;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Infrastructure\Models\JobMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

/**
 * New jobs for a saved search: right away (immediate alerts) or as a daily
 * or weekly digest. Always stored for the in-app notification centre;
 * pushed live when the user keeps real-time on; e-mailed when the alert has
 * e-mail on and the user consented to e-mail alerts.
 */
final class JobAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Jobs listed in full; the rest are counted. */
    private const int MAX_LISTED = 10;

    public int $tries = 3;

    /**
     * @param  list<int>  $jobIds  newest first
     */
    public function __construct(
        public readonly int $savedSearchId,
        public readonly array $jobIds,
        public readonly AlertFrequency $frequency,
    ) {}

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
        $search = $this->search();
        if ($search === null) {
            return [];
        }

        return array_values(array_filter([
            'database',
            $notifiable->wantsRealtimeNotifications() ? 'broadcast' : null,
            $search->email && $notifiable->acceptsEmailAlerts() ? 'mail' : null,
        ]));
    }

    public function toMail(User $notifiable): MailMessage
    {
        $search = $this->search();
        $jobs = $this->jobs($notifiable);
        $unsubscribe = URL::signedRoute('alerts.unsubscribe', ['savedSearch' => $this->savedSearchId]);
        $name = (string) $search?->name;

        return (new MailMessage)
            ->subject(trans_choice("alerts.mail.subject.{$this->frequency->value}", count($this->jobIds), ['count' => count($this->jobIds), 'name' => $name]))
            ->markdown('mail.alerts.jobs', [
                'name' => $name,
                'frequency' => $this->frequency,
                'jobs' => $jobs,
                'total' => count($this->jobIds),
                'listingUrl' => route('jobs.index', $search->filters ?? []),
                'manageUrl' => route('alerts.index'),
                'unsubscribeUrl' => $unsubscribe,
            ])
            // One-click unsubscribe (RFC 8058): mail clients POST to the signed URL.
            ->withSymfonyMessage(function (Email $message) use ($unsubscribe): void {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubscribe}>");
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'job_alert',
            'frequency' => $this->frequency->value,
            'alert_id' => $this->savedSearchId,
            'alert_name' => (string) $this->search()?->name,
            // Opens the listing with the alert's filters.
            'filters' => $this->search()->filters ?? [],
            'count' => count($this->jobIds),
            'jobs' => $this->jobs($notifiable)->take(5)->map(fn (array $job): array => [
                'id' => $job['id'],
                'title' => $job['title'],
                'company' => $job['company'],
                'score' => $job['score'],
            ])->values()->all(),
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'job.alert';
    }

    private function search(): ?SavedSearch
    {
        return SavedSearch::query()->find($this->savedSearchId);
    }

    /**
     * Jobs still visible, with the user's score when there is one.
     *
     * @return Collection<int, array{id: int, title: string, company: string, location: string|null, url: string, score: int|null}>
     */
    private function jobs(User $notifiable): Collection
    {
        $ids = array_slice($this->jobIds, 0, self::MAX_LISTED);
        $scores = JobMatch::query()->where('user_id', $notifiable->id)->whereIn('job_listing_id', $ids)->pluck('score', 'job_listing_id');

        return JobListing::query()->visible()->whereKey($ids)->latestFirst()->get()
            ->map(fn (JobListing $job): array => [
                'id' => $job->id,
                'title' => $job->title,
                'company' => $job->company,
                'location' => $job->location,
                'url' => route('jobs.show', ['job' => $job->id, 'slug' => Str::slug(Str::limit($job->title.' '.$job->company, 80, ''))]),
                'score' => isset($scores[$job->id]) ? (int) $scores[$job->id] : null,
            ]);
    }
}
