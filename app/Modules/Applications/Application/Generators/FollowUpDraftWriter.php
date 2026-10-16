<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;

/**
 * A short follow-up message for an application with no answer yet, in the
 * language and tone of the cover letter the person sent. It is only a draft
 * for the person to copy: nothing is ever sent to the employer.
 */
final readonly class FollowUpDraftWriter
{
    public function write(Application $application): string
    {
        $package = ApplicationPackage::query()
            ->where('user_id', $application->user_id)
            ->where('job_listing_id', $application->job_listing_id)
            ->first();

        $locale = $package?->language;
        $locale = in_array($locale, CoverLetterWriter::TEMPLATE_LANGUAGES, true) ? $locale : ($application->user?->preferredLocale() ?? 'en');
        $locale = in_array($locale, CoverLetterWriter::TEMPLATE_LANGUAGES, true) ? $locale : 'en';
        $tone = $package?->tone->value ?? 'formal';
        $t = fn (string $key, array $params = []): string => (string) __("follow_up.draft.{$key}", $params, $locale);

        $job = $application->job;
        $params = ['title' => $job->title, 'company' => $job->company, 'date' => $application->applied_at?->settings(['locale' => $locale])->translatedFormat('j F Y') ?? ''];

        return implode("\n\n", [
            $t("greeting.{$tone}"),
            $t('body', $params),
            $t("close.{$tone}"),
            $t("sign_off.{$tone}")."\n".$application->user?->name,
        ]);
    }
}
