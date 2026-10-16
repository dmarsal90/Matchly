<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationEvent;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;

/**
 * A short follow-up message for an application with no answer yet, in the
 * language and tone of the cover letter the person sent. It is only a draft
 * for the person to copy: nothing is ever sent to the employer.
 */
final readonly class FollowUpDraftWriter
{
    /**
     * The follow-up message. The second one (after a first follow-up that got
     * no answer) refers back to the first and is shorter and more direct.
     */
    public function write(Application $application): string
    {
        [$locale, $tone, $t] = $this->context($application);

        $firstFollowUp = $application->events->first(fn (ApplicationEvent $event): bool => $event->type === ApplicationEventType::FollowUp);
        $second = $firstFollowUp !== null;
        $date = ($second ? $firstFollowUp->occurred_at : $application->applied_at)?->settings(['locale' => $locale])->isoFormat('LL') ?? '';

        return implode("\n\n", [
            $t("greeting.{$tone}"),
            $t($second ? 'body_second' : 'body', ['title' => $application->job->title, 'company' => $application->job->company, 'date' => $date]),
            $t("close.{$tone}"),
            $t("sign_off.{$tone}")."\n".$application->user?->name,
        ]);
    }

    /** A thank-you note to send after an interview round. */
    public function thanks(Application $application): string
    {
        [, $tone, $t] = $this->context($application);

        return implode("\n\n", [
            $t("greeting.{$tone}"),
            $t('thanks_body', ['title' => $application->job->title, 'company' => $application->job->company]),
            $t("thanks_close.{$tone}"),
            $t("sign_off.{$tone}")."\n".$application->user?->name,
        ]);
    }

    /**
     * Language and tone of the cover letter the person sent, and a translator for them.
     *
     * @return array{0: string, 1: string, 2: callable(string, array<string, string>=): string}
     */
    private function context(Application $application): array
    {
        $package = ApplicationPackage::query()
            ->where('user_id', $application->user_id)
            ->where('job_listing_id', $application->job_listing_id)
            ->first();

        $locale = $package?->language;
        $locale = in_array($locale, CoverLetterWriter::TEMPLATE_LANGUAGES, true) ? $locale : ($application->user?->preferredLocale() ?? 'en');
        $locale = in_array($locale, CoverLetterWriter::TEMPLATE_LANGUAGES, true) ? $locale : 'en';

        return [$locale, $package?->tone->value ?? 'formal', fn (string $key, array $params = []): string => (string) __("follow_up.draft.{$key}", $params, $locale)];
    }
}
