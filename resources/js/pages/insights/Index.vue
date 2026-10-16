<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { NativeSelect } from '@/components/ui/native-select';
import { Label } from '@/components/ui/label';
import {
    index as insightsIndex,
    plan,
    report,
    share as shareRoute,
} from '@/routes/insights';
import { destroy as unshare } from '@/routes/insights/share';
import { index as skillsIndex } from '@/routes/skills';
import { show as showJob } from '@/routes/jobs';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import {
    ChartNoAxesColumn,
    Download,
    ExternalLink,
    FlaskConical,
    Lightbulb,
    Target,
} from '@lucide/vue';
import { getActiveLanguage, trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

type Course = {
    title: string;
    provider: string;
    url: string;
    price: 'free' | 'paid';
    curated: boolean;
};

type SkillGap = {
    skill: string;
    name: string;
    jobs: number;
    required: number;
    tracked: number;
    only_gap: number;
    courses: Course[];
    hours: number;
};

type ClosestJob = {
    id: number;
    slug: string;
    title: string;
    company: string;
    score: number;
    missing: {
        skill: string;
        name: string;
        required: boolean;
        courses: Course[];
        hours: number;
    }[];
};

type Group = {
    name: string;
    applied: number;
    interviews: number;
    offers: number;
};

type Funnel = {
    applied: number;
    stages: { key: string; count: number }[];
    conversion: { from: string; to: string; rate: number | null }[];
    outcomes: Record<string, number>;
    timing: {
        first_response_days: number | null;
        first_interview_days: number | null;
    };
    by_source: Group[];
    by_category: Group[];
    calibration: {
        interview_score: number | null;
        closed_score: number | null;
        interview_count: number;
        closed_count: number;
    };
};

const props = defineProps<{
    skillGaps: {
        jobs: number;
        tracked: number;
        skills: SkillGap[];
        closest: ClosestJob[];
    };
    funnel: Funnel;
    experiments: { key: string; params: Record<string, string> }[];
    shares: { id: number; expires_at: string; views: number }[];
    newShareUrl: string | null;
    studyPlan: {
        hours_per_week: number;
        weeks: {
            week: number;
            starts_on: string;
            total: number;
            items: {
                skill: string;
                name: string;
                hours: number;
                course: { title: string; url: string } | null;
                part: number;
                parts: number;
                continues: boolean;
            }[];
        }[];
        continues: { name: string; hours: number }[];
        left_out: string[];
    };
}>();

const shareForm = useForm({ days: 7, with_name: false });
function createShare(): void {
    shareForm.post(shareRoute.url(), { preserveScroll: true });
}
const shareDate = (value: string): string =>
    new Date(value).toLocaleDateString(getActiveLanguage());

const HOUR_CHOICES = [2, 5, 8, 10, 15, 20];
function changeHours(value: string): void {
    router.get(
        insightsIndex.url(),
        { hours: value },
        {
            only: ['studyPlan'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}
const planHasItems = computed(() =>
    props.studyPlan.weeks.some((week) => week.items.length > 0),
);
const planDate = (value: string): string =>
    new Date(`${value}T00:00:00`).toLocaleDateString(getActiveLanguage(), {
        day: 'numeric',
        month: 'long',
    });

const stageLabel = (key: string): string =>
    trans(`insights.funnel.stages.${key}`);
const categoryLabel = (name: string): string =>
    name === 'other'
        ? trans('insights.funnel.other')
        : trans(`jobs.category.${name}`);
const outcomes = computed(() =>
    Object.entries(props.funnel.outcomes).filter(([, count]) => count > 0),
);

defineOptions({
    layout: { breadcrumbs: [{ title: 'nav.insights', href: insightsIndex() }] },
});

const top = computed(() => props.skillGaps.skills[0] ?? null);
const max = computed(() =>
    Math.max(1, ...props.skillGaps.skills.map((item) => item.jobs)),
);
const stageMax = computed(() => Math.max(1, props.funnel.applied));
</script>

<template>
    <Head :title="trans('insights.title')" />

    <div class="mx-auto w-full max-w-4xl space-y-8 px-4 py-6">
        <Heading
            :title="trans('insights.title')"
            :description="trans('insights.description')"
        />

        <div class="flex flex-wrap items-center gap-3">
            <Button as-child variant="outline" size="sm">
                <a :href="report.url()" download>
                    <Download class="size-4" aria-hidden="true" />
                    {{ trans('insights.report.download') }}
                </a>
            </Button>
            <span class="text-xs text-muted-foreground">{{
                trans('insights.report.download_hint')
            }}</span>
        </div>

        <details class="rounded-xl border p-4">
            <summary class="cursor-pointer text-sm font-medium">
                {{ trans('insights.share.title') }}
            </summary>
            <div class="mt-3 space-y-3">
                <p class="text-sm text-muted-foreground">
                    {{ trans('insights.share.description') }}
                </p>
                <form
                    class="flex flex-wrap items-end gap-3"
                    @submit.prevent="createShare"
                >
                    <div class="grid gap-1.5">
                        <Label for="share-days" class="text-xs">{{
                            trans('insights.share.days')
                        }}</Label>
                        <NativeSelect
                            id="share-days"
                            v-model="shareForm.days"
                            class="h-9 w-32 text-sm"
                        >
                            <option :value="1">
                                {{ trans('insights.share.d1') }}
                            </option>
                            <option :value="7">
                                {{ trans('insights.share.d7') }}
                            </option>
                            <option :value="30">
                                {{ trans('insights.share.d30') }}
                            </option>
                        </NativeSelect>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="share-name"
                            v-model="shareForm.with_name"
                        />
                        <Label for="share-name" class="text-sm font-normal">{{
                            trans('insights.share.with_name')
                        }}</Label>
                    </div>
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="shareForm.processing"
                        >{{ trans('insights.share.create') }}</Button
                    >
                </form>
                <InputError :message="shareForm.errors.days" />

                <div
                    v-if="newShareUrl"
                    class="space-y-1 rounded-lg border border-primary/40 bg-primary/5 p-3"
                    role="status"
                >
                    <p class="text-sm font-medium">
                        {{ trans('insights.share.new') }}
                    </p>
                    <code class="block text-sm break-all">{{
                        newShareUrl
                    }}</code>
                    <p class="text-xs text-muted-foreground">
                        {{ trans('insights.share.new_hint') }}
                    </p>
                </div>

                <div class="space-y-1">
                    <h3 class="text-sm font-medium">
                        {{ trans('insights.share.active') }}
                    </h3>
                    <p
                        v-if="!shares.length"
                        class="text-sm text-muted-foreground"
                    >
                        {{ trans('insights.share.none') }}
                    </p>
                    <ul v-else class="divide-y rounded-lg border text-sm">
                        <li
                            v-for="item in shares"
                            :key="item.id"
                            class="flex flex-wrap items-center justify-between gap-2 p-2"
                        >
                            <span
                                >{{
                                    trans('insights.share.expires', {
                                        date: shareDate(item.expires_at),
                                    })
                                }}
                                ·
                                {{
                                    transChoice(
                                        'insights.share.views',
                                        item.views,
                                    )
                                }}</span
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="
                                    router.delete(unshare.url(item.id), {
                                        preserveScroll: true,
                                    })
                                "
                                >{{ trans('insights.share.revoke') }}</Button
                            >
                        </li>
                    </ul>
                </div>
            </div>
        </details>

        <section
            v-if="experiments.length"
            class="space-y-3"
            aria-labelledby="experiments"
        >
            <div>
                <h2 id="experiments" class="text-lg font-semibold">
                    {{ trans('insights.experiments.title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ trans('insights.experiments.description') }}
                </p>
            </div>
            <ul class="space-y-2">
                <li
                    v-for="idea in experiments"
                    :key="idea.key"
                    class="flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm"
                >
                    <FlaskConical
                        class="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    {{ trans(`insights.experiments.${idea.key}`, idea.params) }}
                </li>
            </ul>
        </section>

        <section class="space-y-4" aria-labelledby="funnel">
            <div>
                <h2 id="funnel" class="text-lg font-semibold">
                    {{ trans('insights.funnel.title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ trans('insights.funnel.description') }}
                </p>
            </div>

            <EmptyState
                v-if="funnel.applied === 0"
                :icon="ChartNoAxesColumn"
                :title="trans('insights.funnel.empty')"
            />

            <template v-else>
                <table class="w-full text-sm">
                    <caption class="sr-only">
                        {{
                            trans('insights.funnel.stages_table')
                        }}
                    </caption>
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th scope="col" class="py-2 pr-3 font-medium">
                                {{ trans('insights.funnel.stage') }}
                            </th>
                            <th scope="col" class="w-2/3 py-2 font-medium">
                                {{ trans('insights.funnel.count') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="stage in funnel.stages" :key="stage.key">
                            <th
                                scope="row"
                                class="py-2 pr-3 text-left font-medium"
                            >
                                {{ stageLabel(stage.key) }}
                            </th>
                            <td class="py-2">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-2 rounded-full bg-primary"
                                        :style="{
                                            width: `${(stage.count / stageMax) * 100}%`,
                                            minWidth: '4px',
                                        }"
                                        aria-hidden="true"
                                    />
                                    <span class="tabular-nums">{{
                                        stage.count
                                    }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <ul class="space-y-1 text-sm">
                    <li v-for="step in funnel.conversion" :key="step.to">
                        {{
                            trans(
                                step.rate === null
                                    ? 'insights.funnel.conversion_none'
                                    : 'insights.funnel.conversion',
                                {
                                    from: stageLabel(step.from),
                                    to: stageLabel(step.to),
                                    rate: String(step.rate ?? ''),
                                },
                            )
                        }}
                    </li>
                </ul>

                <div class="space-y-1 text-sm text-muted-foreground">
                    <template
                        v-if="
                            funnel.timing.first_response_days !== null ||
                            funnel.timing.first_interview_days !== null
                        "
                    >
                        <p v-if="funnel.timing.first_response_days !== null">
                            {{
                                trans('insights.funnel.timing_response', {
                                    days: String(
                                        funnel.timing.first_response_days,
                                    ),
                                })
                            }}
                        </p>
                        <p v-if="funnel.timing.first_interview_days !== null">
                            {{
                                trans('insights.funnel.timing_interview', {
                                    days: String(
                                        funnel.timing.first_interview_days,
                                    ),
                                })
                            }}
                        </p>
                    </template>
                    <p v-else>{{ trans('insights.funnel.timing_none') }}</p>
                </div>

                <div
                    v-if="
                        funnel.calibration.interview_score !== null &&
                        funnel.calibration.closed_score !== null
                    "
                    class="space-y-1 rounded-lg border p-3 text-sm"
                >
                    <h3 class="font-medium">
                        {{ trans('insights.funnel.calibration.title') }}
                    </h3>
                    <p>
                        {{
                            trans('insights.funnel.calibration.summary', {
                                interview: String(
                                    funnel.calibration.interview_score,
                                ),
                                closed: String(funnel.calibration.closed_score),
                            })
                        }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            funnel.calibration.interview_count < 3 ||
                            funnel.calibration.closed_count < 3
                                ? trans('insights.funnel.calibration.few')
                                : funnel.calibration.interview_score >=
                                    funnel.calibration.closed_score
                                  ? trans('insights.funnel.calibration.works')
                                  : trans(
                                        'insights.funnel.calibration.no_signal',
                                    )
                        }}
                    </p>
                </div>

                <div class="space-y-2">
                    <h3 class="text-sm font-medium">
                        {{ trans('insights.funnel.outcomes') }}
                    </h3>
                    <ul class="flex flex-wrap gap-2 text-sm">
                        <li
                            v-for="[key, count] in outcomes"
                            :key="key"
                            class="rounded-full border px-3 py-1"
                        >
                            {{ trans(`insights.funnel.outcome.${key}`) }}:
                            <span class="font-medium tabular-nums">{{
                                count
                            }}</span>
                        </li>
                    </ul>
                </div>

                <div
                    v-for="group in [
                        {
                            id: 'source',
                            title: trans('insights.funnel.by_source'),
                            rows: funnel.by_source,
                            label: (name: string) => name,
                        },
                        {
                            id: 'category',
                            title: trans('insights.funnel.by_category'),
                            rows: funnel.by_category,
                            label: categoryLabel,
                        },
                    ]"
                    :key="group.id"
                >
                    <table class="w-full text-sm">
                        <caption class="py-2 text-left text-sm font-medium">
                            {{
                                group.title
                            }}
                        </caption>
                        <thead class="text-left text-xs text-muted-foreground">
                            <tr>
                                <th scope="col" class="py-2 pr-3 font-medium">
                                    {{ trans('insights.funnel.name') }}
                                </th>
                                <th
                                    scope="col"
                                    class="py-2 pr-3 text-right font-medium"
                                >
                                    {{ trans('insights.funnel.applied') }}
                                </th>
                                <th
                                    scope="col"
                                    class="py-2 pr-3 text-right font-medium"
                                >
                                    {{ trans('insights.funnel.interviews') }}
                                </th>
                                <th
                                    scope="col"
                                    class="py-2 text-right font-medium"
                                >
                                    {{ trans('insights.funnel.offers') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in group.rows" :key="row.name">
                                <th
                                    scope="row"
                                    class="py-2 pr-3 text-left font-medium"
                                >
                                    {{ group.label(row.name) }}
                                </th>
                                <td class="py-2 pr-3 text-right tabular-nums">
                                    {{ row.applied }}
                                </td>
                                <td class="py-2 pr-3 text-right tabular-nums">
                                    {{ row.interviews }}
                                </td>
                                <td class="py-2 text-right tabular-nums">
                                    {{ row.offers }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-if="funnel.applied < 10"
                    class="text-xs text-muted-foreground"
                >
                    {{ trans('insights.funnel.small_sample') }}
                </p>
            </template>
        </section>

        <section class="space-y-4" aria-labelledby="skill-gaps">
            <div>
                <h2 id="skill-gaps" class="text-lg font-semibold">
                    {{ trans('insights.skill_gaps.title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ trans('insights.skill_gaps.description') }}
                </p>
            </div>

            <EmptyState
                v-if="!top"
                :icon="Target"
                :title="trans('insights.skill_gaps.empty')"
            >
                <Button as-child size="sm"
                    ><Link :href="skillsIndex()">{{
                        trans('nav.skills')
                    }}</Link></Button
                >
            </EmptyState>

            <template v-else>
                <div
                    class="flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4"
                >
                    <Lightbulb
                        class="mt-0.5 size-5 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div class="space-y-1">
                        <p class="font-medium">
                            {{
                                trans('insights.skill_gaps.next', {
                                    skill: top.name,
                                })
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                transChoice(
                                    'insights.skill_gaps.next_hint',
                                    top.only_gap,
                                    {
                                        count: String(top.only_gap),
                                        jobs: String(top.jobs),
                                    },
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div v-if="skillGaps.closest.length" class="space-y-3">
                    <div>
                        <h3 class="font-medium">
                            {{ trans('insights.closest.title') }}
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ trans('insights.closest.description') }}
                        </p>
                    </div>
                    <article
                        v-for="job in skillGaps.closest"
                        :key="job.id"
                        class="space-y-3 rounded-xl border p-4"
                    >
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <Link
                                :href="showJob({ job: job.id, slug: job.slug })"
                                class="font-medium hover:underline"
                                >{{ job.title }} · {{ job.company }}</Link
                            >
                            <span class="text-sm text-muted-foreground">{{
                                trans('insights.closest.score', {
                                    score: String(job.score),
                                })
                            }}</span>
                        </div>
                        <p class="text-sm">
                            {{
                                transChoice(
                                    'insights.closest.missing',
                                    job.missing.length,
                                    { count: String(job.missing.length) },
                                )
                            }}
                        </p>
                        <div
                            v-for="skill in job.missing"
                            :key="skill.skill"
                            class="rounded-lg bg-muted/30 p-3"
                        >
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                {{ skill.name }}
                                <Badge variant="outline">{{
                                    skill.required
                                        ? trans('insights.closest.required')
                                        : trans('insights.closest.nice')
                                }}</Badge>
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >{{
                                        trans('insights.courses.hours', {
                                            hours: String(skill.hours),
                                        })
                                    }}</span
                                >
                            </p>
                            <ul
                                class="mt-2 space-y-1 text-sm"
                                :aria-label="trans('insights.courses.list')"
                            >
                                <li
                                    v-for="course in skill.courses"
                                    :key="course.url"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <Badge
                                        :variant="
                                            course.price === 'free'
                                                ? 'default'
                                                : 'outline'
                                        "
                                        >{{
                                            trans(
                                                `insights.courses.${course.price}`,
                                            )
                                        }}</Badge
                                    >
                                    <a
                                        :href="course.url"
                                        target="_blank"
                                        rel="noopener noreferrer nofollow"
                                        class="inline-flex items-center gap-1 underline underline-offset-2"
                                        >{{ course.title }}
                                        <span class="text-muted-foreground"
                                            >· {{ course.provider }}</span
                                        >
                                        <ExternalLink
                                            class="size-3"
                                            aria-hidden="true"
                                    /></a>
                                    <span
                                        v-if="!course.curated"
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            trans('insights.courses.search')
                                        }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </article>
                </div>

                <p class="text-xs text-muted-foreground">
                    {{
                        trans('insights.skill_gaps.basis', {
                            jobs: String(skillGaps.jobs),
                            tracked: String(skillGaps.tracked),
                        })
                    }}
                </p>

                <table class="w-full text-sm">
                    <caption class="sr-only">
                        {{
                            trans('insights.skill_gaps.table')
                        }}
                    </caption>
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th scope="col" class="py-2 pr-3 font-medium">
                                {{ trans('insights.skill_gaps.skill') }}
                            </th>
                            <th scope="col" class="w-1/3 py-2 pr-3 font-medium">
                                {{ trans('insights.skill_gaps.jobs') }}
                            </th>
                            <th
                                scope="col"
                                class="py-2 pr-3 text-right font-medium"
                            >
                                {{ trans('insights.skill_gaps.required') }}
                            </th>
                            <th
                                scope="col"
                                class="py-2 pr-3 text-right font-medium"
                            >
                                {{ trans('insights.skill_gaps.only_gap') }}
                            </th>
                            <th scope="col" class="py-2 text-right font-medium">
                                {{ trans('insights.skill_gaps.tracked') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <template
                            v-for="item in skillGaps.skills"
                            :key="item.skill"
                        >
                            <tr>
                                <th
                                    scope="row"
                                    class="py-2 pr-3 text-left font-medium"
                                >
                                    {{ item.name }}
                                </th>
                                <td class="py-2 pr-3">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-2 rounded-full bg-primary"
                                            :style="{
                                                width: `${(item.jobs / max) * 100}%`,
                                                minWidth: '4px',
                                            }"
                                            aria-hidden="true"
                                        />
                                        <span class="tabular-nums">{{
                                            item.jobs
                                        }}</span>
                                    </div>
                                </td>
                                <td class="py-2 pr-3 text-right tabular-nums">
                                    {{ item.required }}
                                </td>
                                <td class="py-2 pr-3 text-right tabular-nums">
                                    {{ item.only_gap }}
                                </td>
                                <td class="py-2 text-right tabular-nums">
                                    {{ item.tracked }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="5" class="pb-3">
                                    <details>
                                        <summary
                                            class="cursor-pointer text-xs text-muted-foreground"
                                        >
                                            {{
                                                trans(
                                                    'insights.courses.learn',
                                                    { skill: item.name },
                                                )
                                            }}
                                            ·
                                            {{
                                                trans(
                                                    'insights.courses.hours',
                                                    {
                                                        hours: String(
                                                            item.hours,
                                                        ),
                                                    },
                                                )
                                            }}
                                        </summary>
                                        <ul
                                            class="mt-2 space-y-1 text-sm"
                                            :aria-label="
                                                trans('insights.courses.list')
                                            "
                                        >
                                            <li
                                                v-for="course in item.courses"
                                                :key="course.url"
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <Badge
                                                    :variant="
                                                        course.price === 'free'
                                                            ? 'default'
                                                            : 'outline'
                                                    "
                                                    >{{
                                                        trans(
                                                            `insights.courses.${course.price}`,
                                                        )
                                                    }}</Badge
                                                >
                                                <a
                                                    :href="course.url"
                                                    target="_blank"
                                                    rel="noopener noreferrer nofollow"
                                                    class="inline-flex items-center gap-1 underline underline-offset-2"
                                                    >{{ course.title }}
                                                    <span
                                                        class="text-muted-foreground"
                                                        >·
                                                        {{
                                                            course.provider
                                                        }}</span
                                                    >
                                                    <ExternalLink
                                                        class="size-3"
                                                        aria-hidden="true"
                                                /></a>
                                                <span
                                                    v-if="!course.curated"
                                                    class="text-xs text-muted-foreground"
                                                    >{{
                                                        trans(
                                                            'insights.courses.search',
                                                        )
                                                    }}</span
                                                >
                                            </li>
                                        </ul>
                                    </details>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>
        </section>

        <section v-if="top" class="space-y-4" aria-labelledby="study-plan">
            <div>
                <h2 id="study-plan" class="text-lg font-semibold">
                    {{ trans('insights.plan.title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ trans('insights.plan.description') }}
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1.5">
                    <Label for="plan-hours" class="text-xs">{{
                        trans('insights.plan.hours')
                    }}</Label>
                    <NativeSelect
                        id="plan-hours"
                        class="h-9 w-24 text-sm"
                        :model-value="studyPlan.hours_per_week"
                        @update:model-value="changeHours(String($event))"
                    >
                        <option
                            v-for="choice in HOUR_CHOICES"
                            :key="choice"
                            :value="choice"
                        >
                            {{ choice }}
                        </option>
                    </NativeSelect>
                </div>
                <Button
                    v-if="planHasItems"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <a
                        :href="
                            plan.url({
                                query: { hours: studyPlan.hours_per_week },
                            })
                        "
                        download
                    >
                        <Download class="size-4" aria-hidden="true" />
                        {{ trans('insights.plan.download') }}
                    </a>
                </Button>
            </div>
            <p v-if="planHasItems" class="text-xs text-muted-foreground">
                {{ trans('insights.plan.download_hint') }}
            </p>

            <p v-if="!planHasItems" class="text-sm">
                {{ trans('insights.plan.empty') }}
            </p>
            <ol v-else class="grid gap-3 sm:grid-cols-2">
                <li
                    v-for="week in studyPlan.weeks"
                    :key="week.week"
                    class="space-y-2 rounded-xl border p-4"
                >
                    <h3 class="font-medium">
                        {{
                            trans('insights.plan.week', {
                                n: String(week.week),
                                date: planDate(week.starts_on),
                            })
                        }}
                    </h3>
                    <ul v-if="week.items.length" class="space-y-1 text-sm">
                        <li
                            v-for="item in week.items"
                            :key="item.skill + item.part"
                        >
                            <span class="font-medium">{{ item.name }}</span>
                            · ≈ {{ item.hours }} h
                            <span
                                v-if="item.parts > 1"
                                class="text-muted-foreground"
                                >({{
                                    trans('insights.plan.part', {
                                        part: String(item.part),
                                        parts: String(item.parts),
                                    })
                                }})</span
                            >
                            <a
                                v-if="item.course"
                                :href="item.course.url"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                class="ml-1 underline underline-offset-2"
                                >{{ item.course.title }}</a
                            >
                        </li>
                    </ul>
                    <p class="text-xs text-muted-foreground">
                        {{
                            trans('insights.plan.total', {
                                hours: String(week.total),
                            })
                        }}
                    </p>
                </li>
            </ol>
            <p
                v-if="studyPlan.continues.length"
                class="text-sm text-muted-foreground"
            >
                {{
                    trans('insights.plan.continues', {
                        skills: studyPlan.continues
                            .map((item) => `${item.name} (≈ ${item.hours} h)`)
                            .join(', '),
                    })
                }}
            </p>
            <p
                v-if="studyPlan.left_out.length"
                class="text-sm text-muted-foreground"
            >
                {{
                    trans('insights.plan.left_out', {
                        skills: studyPlan.left_out.join(', '),
                    })
                }}
            </p>
        </section>
    </div>
</template>
