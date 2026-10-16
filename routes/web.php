<?php

declare(strict_types=1);

use App\Modules\Alerts\Http\Controllers\AlertController;
use App\Modules\Alerts\Http\Controllers\UnsubscribeController;
use App\Modules\Applications\Http\Controllers\ApplicationController;
use App\Modules\Applications\Http\Controllers\ApplicationEventController;
use App\Modules\Applications\Http\Controllers\CompanyNoteController;
use App\Modules\Applications\Http\Controllers\InsightsController;
use App\Modules\Applications\Http\Controllers\InterviewPrepController;
use App\Modules\Applications\Http\Controllers\QuickApplyController;
use App\Modules\Applications\Http\Controllers\SavedJobController;
use App\Modules\Identity\Http\Controllers\OAuthController;
use App\Modules\Jobs\Http\Controllers\JobController;
use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Profiles\Domain\Enums\QuestionnaireStep;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Http\Controllers\CvDocumentController;
use App\Modules\Profiles\Http\Controllers\OnboardingController;
use App\Modules\Profiles\Http\Controllers\ProfileController;
use App\Modules\Profiles\Http\Controllers\ProfileImportController;
use App\Modules\Profiles\Http\Controllers\SearchProfileController;
use App\Modules\Profiles\Http\Controllers\SkillsController;
use App\Modules\Profiles\Http\Controllers\SourceDataController;
use App\Modules\Shared\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::put('locale', [LocaleController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('locale.update');

// Public: listings are never gated behind sign-up.
Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('jobs/{job}/{slug?}', [JobController::class, 'show'])->whereNumber('job')->name('jobs.show');

Route::inertia('privacy', 'legal/Privacy', ['retention' => [
    'days' => (string) config('privacy.retention.unverified_account_days'),
    'months' => (string) config('privacy.retention.inactive_cv_text_months'),
]])->name('legal.privacy');
Route::inertia('terms', 'legal/Terms')->name('legal.terms');

// One-click unsubscribe from alert e-mails: signed links, no sign-in needed.
Route::middleware(['signed', 'throttle:30,1'])->group(function () {
    Route::get('alerts/{savedSearch}/unsubscribe', [UnsubscribeController::class, 'show'])->name('alerts.unsubscribe');
    Route::post('alerts/{savedSearch}/unsubscribe', [UnsubscribeController::class, 'oneClick'])->name('alerts.unsubscribe.one-click');
});

Route::middleware('throttle:oauth')->group(function () {
    Route::get('auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // The listing is the app's home once signed in.
    Route::get('dashboard', fn () => to_route('jobs.index'))->name('dashboard');

    Route::post('jobs/{job}/save', [SavedJobController::class, 'toggle'])->whereNumber('job')->middleware('throttle:60,1')->name('jobs.save');
    Route::post('company-notes', [CompanyNoteController::class, 'store'])->middleware('throttle:60,1')->name('company-notes.store');
    Route::post('company-notes/discover', [CompanyNoteController::class, 'discover'])->middleware('throttle:6,60')->name('company-notes.discover');
    Route::post('company-notes/{note}/approve', [CompanyNoteController::class, 'approve'])->whereNumber('note')->name('company-notes.approve');
    Route::delete('company-notes/{note}', [CompanyNoteController::class, 'destroy'])->whereNumber('note')->name('company-notes.destroy');
    Route::get('insights', [InsightsController::class, 'index'])->name('insights.index');
    Route::get('insights/report', [InsightsController::class, 'report'])->middleware('throttle:30,1')->name('insights.report');
    Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::patch('applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');
    Route::patch('applications/{application}/deadline', [ApplicationController::class, 'deadline'])->whereNumber('application')->name('applications.deadline');
    Route::delete('applications/{application}', [ApplicationController::class, 'destroy'])->name('applications.destroy');
    Route::post('applications/{application}/events', [ApplicationEventController::class, 'store'])->name('applications.events.store');
    Route::get('applications/{application}/interview-prep', [InterviewPrepController::class, 'show'])->whereNumber('application')->name('applications.interview-prep.show');
    Route::post('applications/{application}/interview-prep', [InterviewPrepController::class, 'store'])->whereNumber('application')->middleware('throttle:quick-apply')->name('applications.interview-prep.store');

    // Quick apply: a package is prepared for review; the person applies on the employer's site.
    Route::post('jobs/{job}/quick-apply', [QuickApplyController::class, 'start'])->whereNumber('job')->middleware('throttle:quick-apply')->name('jobs.quick-apply');
    Route::get('applications/packages/{package}', [QuickApplyController::class, 'show'])->name('applications.packages.show');
    Route::put('applications/packages/{package}', [QuickApplyController::class, 'update'])->name('applications.packages.update');
    Route::post('applications/packages/{package}/approve', [QuickApplyController::class, 'approve'])->name('applications.packages.approve');
    Route::post('applications/packages/{package}/applied', [QuickApplyController::class, 'applied'])->name('applications.packages.applied');
    Route::post('applications/packages/{package}/retry', [QuickApplyController::class, 'retry'])->middleware('throttle:quick-apply')->name('applications.packages.retry');
    Route::get('applications/packages/{package}/letter.txt', [QuickApplyController::class, 'letter'])->name('applications.packages.letter');
    Route::get('applications/packages/{package}/resume.pdf', [QuickApplyController::class, 'resume'])->middleware('throttle:30,1')->name('applications.packages.resume');
    Route::delete('applications/packages/{package}', [QuickApplyController::class, 'destroy'])->name('applications.packages.destroy');

    // Professional profile ("career"); account settings keep the starter kit's profile.* names.
    Route::get('profile', [ProfileController::class, 'edit'])->name('career.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('career.update');

    Route::post('profile/cv', [CvDocumentController::class, 'store'])->middleware('throttle:uploads')->name('career.cv.store');
    Route::get('profile/cv/{cv}', [CvDocumentController::class, 'download'])->name('career.cv.download');
    Route::patch('profile/cv/{cv}/primary', [CvDocumentController::class, 'primary'])->name('career.cv.primary');
    Route::delete('profile/cv/{cv}', [CvDocumentController::class, 'destroy'])->name('career.cv.destroy');

    // Saved-search alerts and the notification centre.
    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('alerts', [AlertController::class, 'store'])->middleware('throttle:30,1')->name('alerts.store');
    Route::put('alerts/{alert}', [AlertController::class, 'update'])->name('alerts.update');
    Route::delete('alerts/{alert}', [AlertController::class, 'destroy'])->name('alerts.destroy');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');

    // Skills questionnaire (onboarding): one step per request, editable later.
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::put('onboarding/{step}', [OnboardingController::class, 'update'])
        ->whereIn('step', array_column(QuestionnaireStep::cases(), 'value'))
        ->name('onboarding.update');

    // Merged skill profile, its sources (GitHub, LinkedIn export, CV) and the search profile.
    Route::get('profile/skills', [SkillsController::class, 'index'])->name('skills.index');
    Route::post('profile/imports/github', [ProfileImportController::class, 'github'])->middleware('throttle:imports')->name('skills.imports.github');
    Route::post('profile/imports/linkedin', [ProfileImportController::class, 'linkedin'])->middleware('throttle:uploads')->name('skills.imports.linkedin');
    Route::post('profile/imports/cv', [ProfileImportController::class, 'cv'])->middleware('throttle:imports')->name('skills.imports.cv');
    Route::get('profile/imports/{import}', [ProfileImportController::class, 'show'])->name('skills.imports.show');
    Route::post('profile/imports/{import}/confirm', [ProfileImportController::class, 'confirm'])->name('skills.imports.confirm');
    Route::delete('profile/imports/{import}', [ProfileImportController::class, 'destroy'])->name('skills.imports.destroy');
    Route::delete('profile/sources/{source}', [SourceDataController::class, 'destroy'])
        ->whereIn('source', array_column(SkillSource::cases(), 'value'))
        ->name('skills.sources.destroy');
    Route::put('profile/search', [SearchProfileController::class, 'update'])->name('skills.search.update');
    Route::post('profile/search/regenerate', [SearchProfileController::class, 'regenerate'])->name('skills.search.regenerate');
});

require __DIR__.'/settings.php';
