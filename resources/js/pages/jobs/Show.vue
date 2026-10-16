<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CompanyNotes from '@/features/applications/components/CompanyNotes.vue';
import QuickApplyButton from '@/features/applications/components/QuickApplyButton.vue';
import type {
    CompanyNoteEntry,
    QuickApplyOptions,
} from '@/features/applications/types';
import ApplyButton from '@/features/jobs/components/ApplyButton.vue';
import JobRequirementsPanel from '@/features/jobs/components/JobRequirementsPanel.vue';
import MatchBreakdown from '@/features/jobs/components/MatchBreakdown.vue';
import SalaryBenchmark, {
    type SalaryBenchmark as Benchmark,
} from '@/features/jobs/components/SalaryBenchmark.vue';
import SalaryLabel from '@/features/jobs/components/SalaryLabel.vue';
import SaveJobButton from '@/features/jobs/components/SaveJobButton.vue';
import { relativeTime } from '@/features/jobs/lib/format';
import type { Job, JobMatch, JobRequirements } from '@/features/jobs/types';
import { index } from '@/routes/jobs';
import { destroy as unfollow, store as follow } from '@/routes/radar';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Radar } from '@lucide/vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    job: Job;
    isOpen: boolean;
    requirements: JobRequirements;
    match: JobMatch | null;
    salaryBenchmark: Benchmark | null;
    followedCompanyId: number | null;
    quickApply: QuickApplyOptions | null;
    companyNotes: CompanyNoteEntry[];
}>();

// Public page: guests see the requirements, signed-in users their score too.
const signedIn = computed(() => usePage().props.auth.user !== null);

function toggleFollow(): void {
    if (props.followedCompanyId !== null) {
        router.delete(unfollow.url(props.followedCompanyId), {
            preserveScroll: true,
        });
    } else {
        router.post(
            follow.url(),
            { company: props.job.company },
            { preserveScroll: true },
        );
    }
}

const posted = computed(() =>
    relativeTime(props.job.posted_at, getActiveLanguage()),
);
const regions = computed(() => {
    const names = new Intl.DisplayNames([getActiveLanguage()], {
        type: 'region',
    });

    return props.job.allowed_regions
        .map((code) =>
            code.length === 2
                ? (names.of(code) ?? code)
                : code.replace('_', ' '),
        )
        .join(', ');
});
const description = computed(() => `${props.job.title} · ${props.job.company}`);
</script>

<template>
    <Head :title="job.title">
        <meta
            head-key="description"
            name="description"
            :content="description"
        />
        <meta
            v-if="!isOpen"
            head-key="robots"
            name="robots"
            content="noindex"
        />
    </Head>

    <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6">
        <Button as-child variant="ghost" size="sm" class="-ml-2">
            <Link :href="index()"
                ><ArrowLeft class="size-4" aria-hidden="true" />{{
                    trans('jobs.show.back')
                }}</Link
            >
        </Button>

        <div
            v-if="!isOpen"
            role="status"
            class="flex gap-3 rounded-lg border border-amber-300/50 bg-amber-50 p-4 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <AlertTriangle class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div>
                <p class="font-medium">{{ trans('jobs.show.closed') }}</p>
                <p class="text-sm opacity-80">
                    {{ trans('jobs.show.closed_hint') }}
                </p>
            </div>
        </div>

        <header class="space-y-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight text-balance">
                    {{ job.title }}
                </h1>
                <p
                    class="flex flex-wrap items-center gap-2 text-lg text-muted-foreground"
                >
                    {{ job.company }}
                    <Button
                        v-if="signedIn"
                        variant="outline"
                        size="sm"
                        :aria-pressed="followedCompanyId !== null"
                        @click="toggleFollow"
                    >
                        <Radar class="size-4" aria-hidden="true" />
                        {{
                            followedCompanyId !== null
                                ? trans('radar.following')
                                : trans('radar.follow')
                        }}
                    </Button>
                </p>
            </div>

            <div class="flex flex-wrap gap-1.5">
                <Badge variant="secondary">{{
                    trans(`jobs.work_mode.${job.work_mode}`)
                }}</Badge>
                <Badge v-if="job.employment_type" variant="outline">{{
                    trans(`jobs.type.${job.employment_type}`)
                }}</Badge>
                <Badge v-if="job.seniority" variant="outline">{{
                    trans(`jobs.seniority.${job.seniority}`)
                }}</Badge>
                <Badge v-if="job.role" variant="outline">{{
                    trans(`jobs.role.${job.role}`)
                }}</Badge>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <ApplyButton
                    v-if="isOpen"
                    :href="job.apply_url"
                    size="default"
                />
                <QuickApplyButton
                    v-if="isOpen"
                    :job="job"
                    :options="quickApply"
                    size="default"
                />
                <SaveJobButton
                    :job-id="job.id"
                    :tracking-status="job.tracking_status"
                    :reload="['job']"
                    with-label
                />
            </div>
        </header>

        <section class="rounded-xl border p-4">
            <h2 class="sr-only">{{ trans('jobs.show.details') }}</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">
                        {{ trans('jobs.filters.scope') }}
                    </dt>
                    <dd>
                        {{
                            job.remote_scope
                                ? trans(`jobs.scope.${job.remote_scope}`)
                                : trans('jobs.scope.unknown')
                        }}<template v-if="regions"> · {{ regions }}</template>
                    </dd>
                </div>
                <div v-if="job.location">
                    <dt class="text-muted-foreground">
                        {{ trans('jobs.show.location') }}
                    </dt>
                    <dd>{{ job.location }}</dd>
                </div>
                <div v-if="job.salary">
                    <dt class="text-muted-foreground">
                        {{ trans('jobs.filters.salary') }}
                    </dt>
                    <dd><SalaryLabel :salary="job.salary" /></dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ trans('jobs.filters.posted') }}
                    </dt>
                    <dd>
                        <time :datetime="job.posted_at">{{ posted }}</time>
                    </dd>
                </div>
            </dl>
            <ul v-if="job.tags.length" class="mt-4 flex flex-wrap gap-1.5">
                <li
                    v-for="tag in job.tags"
                    :key="tag"
                    class="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                >
                    {{ tag }}
                </li>
            </ul>
        </section>

        <SalaryBenchmark v-if="salaryBenchmark" :benchmark="salaryBenchmark" />

        <MatchBreakdown :match="match" :signed-in="signedIn" />

        <JobRequirementsPanel :requirements="requirements" />

        <CompanyNotes
            v-if="signedIn"
            :company="job.company"
            :notes="companyNotes"
        />

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">
                {{ trans('jobs.show.about') }}
            </h2>
            <!-- eslint-disable vue/no-v-html -->
            <!-- Sanitized with HTMLPurifier at ingestion (allow-list of formatting tags, hardened links). -->
            <div
                class="job-description max-w-none text-sm leading-relaxed"
                v-html="job.description"
            />
            <!-- eslint-enable vue/no-v-html -->
        </section>

        <footer
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/30 p-4 text-sm text-muted-foreground"
        >
            <p>
                {{
                    trans('jobs.show.source_notice', {
                        source: job.source.name,
                    })
                }}
            </p>
            <a
                :href="job.source.url"
                target="_blank"
                rel="noopener"
                class="font-medium text-foreground underline underline-offset-4"
            >
                {{
                    trans('jobs.show.view_source', { source: job.source.name })
                }}
            </a>
        </footer>
    </div>
</template>
