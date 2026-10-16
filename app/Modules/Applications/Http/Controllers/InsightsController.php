<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Queries\ApplicationFunnel;
use App\Modules\Applications\Application\Queries\ExperimentAdvisor;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Matching\Application\Queries\SkillGapReport;
use App\Modules\Matching\Application\Queries\StudyPlan;
use App\Modules\Shared\Domain\Services\IcsCalendar;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** What the person's job search says about them: skills to learn next. */
final class InsightsController
{
    /** How many of the top missing skills the study plan tries to fit. */
    private const int PLAN_SKILLS = 6;

    public function index(Request $request, #[CurrentUser] User $user, SkillGapReport $gaps, ApplicationFunnel $funnel, ExperimentAdvisor $advisor, StudyPlan $plan): Response
    {
        $figures = $funnel->for($user->id);
        $skillGaps = $gaps->for($user->id);

        return Inertia::render('insights/Index', [
            'skillGaps' => $skillGaps,
            'studyPlan' => $plan->build($this->planSkills($skillGaps), $this->hoursPerWeek($request)),
            'funnel' => $figures,
            'experiments' => $advisor->advise($figures),
        ]);
    }

    /** The study plan as a calendar file: one all-day event per skill and week. */
    public function plan(Request $request, #[CurrentUser] User $user, SkillGapReport $gaps, StudyPlan $plan): HttpResponse
    {
        $built = $plan->build($this->planSkills($gaps->for($user->id)), $this->hoursPerWeek($request));
        $calendar = new IcsCalendar((string) config('app.name'), new DateTimeImmutable);

        foreach ($built['weeks'] as $week) {
            foreach ($week['items'] as $item) {
                $part = $item['parts'] > 1 ? ' ('.$item['part'].'/'.$item['parts'].')' : '';
                $description = __('insights.plan.event_hours', ['hours' => $item['hours']]).($item['course'] !== null ? "\n".__('insights.plan.event_course', ['title' => $item['course']['title']]) : '');
                $calendar->allDay(
                    'plan-'.$user->id.'-'.$week['starts_on'].'-'.$item['skill'].'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
                    new DateTimeImmutable($week['starts_on']),
                    __('insights.plan.event_title', ['skill' => $item['name']]).$part,
                    $description,
                    $item['course']['url'] ?? null,
                );
            }
        }

        return response($calendar->render(), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug((string) config('app.name')).'-study-plan.ics"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** The same figures as one self-contained HTML file to keep, print or share. */
    public function report(#[CurrentUser] User $user, SkillGapReport $gaps, ApplicationFunnel $funnel, ExperimentAdvisor $advisor): HttpResponse
    {
        $now = CarbonImmutable::now();
        $figures = $funnel->for($user->id);
        $html = view('reports.insights', [
            'name' => $user->name,
            'generatedAt' => $now->settings(['locale' => app()->getLocale()])->isoFormat('LL'),
            'gaps' => $gaps->for($user->id),
            'funnel' => $figures,
            'experiments' => $advisor->advise($figures),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug((string) config('app.name')).'-report-'.$now->toDateString().'.html"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The best-ranked missing skills, as the study plan wants them.
     *
     * @param  array{skills: list<array<string, mixed>>}  $skillGaps
     * @return list<array{skill: string, name: string, hours: int, courses: list<array{title: string, url: string, price: string, curated: bool}>}>
     */
    private function planSkills(array $skillGaps): array
    {
        return array_map(fn (array $skill): array => ['skill' => $skill['skill'], 'name' => $skill['name'], 'hours' => $skill['hours'], 'courses' => $skill['courses']], array_slice($skillGaps['skills'], 0, self::PLAN_SKILLS));
    }

    private function hoursPerWeek(Request $request): int
    {
        return max(StudyPlan::MIN_HOURS, min(StudyPlan::MAX_HOURS, $request->integer('hours', StudyPlan::DEFAULT_HOURS)));
    }
}
