<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import ApplicationTimeline from '@/features/applications/components/ApplicationTimeline.vue';
import type { ApplicationEventEntry } from '@/features/applications/types';
import ApplyButton from '@/features/jobs/components/ApplyButton.vue';
import { show as showPrep } from '@/routes/applications/interview-prep';
import { show as showPackage } from '@/routes/applications/packages';
import type { Job } from '@/features/jobs/types';
import {
    destroy,
    index as applicationsIndex,
    update,
} from '@/routes/applications';
import { index as jobsIndex, show } from '@/routes/jobs';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, GraduationCap, Star, Trash2 } from '@lucide/vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { computed, reactive } from 'vue';

type TrackedApplication = {
    id: number;
    status: string;
    notes: string | null;
    status_changed_at: string;
    applied_at: string | null;
    job_open: boolean;
    job: Job;
    events: ApplicationEventEntry[];
    follow_up_draft: string | null;
    thank_you_draft: string | null;
    package: { id: number; status: string } | null;
};

const props = defineProps<{
    applications: TrackedApplication[];
    statuses: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.applications', href: applicationsIndex() }],
    },
});

const notes = reactive<Record<number, string>>(
    Object.fromEntries(
        props.applications.map((item) => [item.id, item.notes ?? '']),
    ),
);

const columns = computed(() =>
    props.statuses.map((status) => ({
        status,
        items: props.applications.filter((item) => item.status === status),
    })),
);

function save(item: TrackedApplication, status: string): void {
    router.patch(
        update.url(item.id),
        { status, notes: notes[item.id] || null },
        { preserveScroll: true, only: ['applications'] },
    );
}

function remove(item: TrackedApplication): void {
    if (window.confirm(trans('applications.remove_confirm'))) {
        router.delete(destroy.url(item.id), {
            preserveScroll: true,
            only: ['applications'],
        });
    }
}

const formatDate = (iso: string): string =>
    new Intl.DateTimeFormat(getActiveLanguage(), {
        dateStyle: 'medium',
    }).format(new Date(iso));
</script>

<template>
    <Head :title="trans('applications.title')" />

    <div class="space-y-6 px-4 py-6">
        <Heading
            :title="trans('applications.title')"
            :description="trans('applications.description')"
        />

        <EmptyState
            v-if="applications.length === 0"
            :icon="Star"
            :title="trans('applications.empty')"
        >
            <Button as-child size="sm"
                ><Link :href="jobsIndex()">{{
                    trans('applications.browse')
                }}</Link></Button
            >
        </EmptyState>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <section
                v-for="column in columns"
                :key="column.status"
                class="space-y-3 rounded-xl bg-muted/40 p-3"
                :aria-labelledby="`column-${column.status}`"
            >
                <h2
                    :id="`column-${column.status}`"
                    class="flex items-center justify-between px-1 text-sm font-semibold"
                >
                    {{ trans(`applications.status.${column.status}`) }}
                    <span class="text-muted-foreground">{{
                        column.items.length
                    }}</span>
                </h2>

                <article
                    v-for="item in column.items"
                    :key="item.id"
                    class="space-y-3 rounded-lg border bg-card p-3"
                >
                    <div class="space-y-0.5">
                        <Link
                            :href="
                                show({ job: item.job.id, slug: item.job.slug })
                            "
                            class="leading-snug font-medium hover:underline"
                            >{{ item.job.title }}</Link
                        >
                        <p class="text-sm text-muted-foreground">
                            {{ item.job.company }}
                        </p>
                        <p
                            v-if="item.applied_at"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                trans('applications.applied_on', {
                                    date: formatDate(item.applied_at),
                                })
                            }}
                        </p>
                        <Badge
                            v-if="!item.job_open"
                            variant="outline"
                            class="mt-1"
                            >{{ trans('applications.job_closed') }}</Badge
                        >
                    </div>

                    <div class="grid gap-1">
                        <Label :for="`status-${item.id}`" class="text-xs">{{
                            trans('applications.move_to')
                        }}</Label>
                        <NativeSelect
                            :id="`status-${item.id}`"
                            :model-value="item.status"
                            class="h-8 text-sm"
                            @update:model-value="save(item, String($event))"
                        >
                            <option
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ trans(`applications.status.${status}`) }}
                            </option>
                        </NativeSelect>
                    </div>

                    <details>
                        <summary
                            class="cursor-pointer text-xs text-muted-foreground"
                        >
                            {{ trans('applications.notes') }}
                        </summary>
                        <div class="mt-2 grid gap-2">
                            <textarea
                                v-model="notes[item.id]"
                                rows="3"
                                maxlength="5000"
                                :aria-label="trans('applications.notes')"
                                :placeholder="
                                    trans('applications.notes_placeholder')
                                "
                                class="w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <Button
                                size="sm"
                                variant="secondary"
                                @click="save(item, item.status)"
                                >{{ trans('applications.save_notes') }}</Button
                            >
                        </div>
                    </details>

                    <ApplicationTimeline
                        :application-id="item.id"
                        :events="item.events"
                        :follow-up-draft="item.follow_up_draft"
                        :thank-you-draft="item.thank_you_draft"
                    />

                    <div class="flex items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <ApplyButton
                                v-if="item.job_open"
                                :href="item.job.apply_url"
                            />
                            <Button
                                v-if="
                                    ['applied', 'interview', 'offer'].includes(
                                        item.status,
                                    )
                                "
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link :href="showPrep(item.id)">
                                    <GraduationCap
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    {{ trans('interview_prep.open') }}
                                </Link>
                            </Button>
                            <Button
                                v-if="item.package"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link :href="showPackage(item.package.id)">
                                    <FileText
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    {{ trans('applications.open_package') }}
                                </Link>
                            </Button>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="ml-auto"
                            :aria-label="trans('applications.remove')"
                            :title="trans('applications.remove')"
                            @click="remove(item)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                </article>
            </section>
        </div>
    </div>
</template>
