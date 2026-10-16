<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import ContactCard from '@/features/applications/components/ContactCard.vue';
import CoverLetterEditor from '@/features/applications/components/CoverLetterEditor.vue';
import CvEditor from '@/features/applications/components/CvEditor.vue';
import type {
    ApplicationPackage,
    PackageWarning,
    Resume,
} from '@/features/applications/types';
import { relativeTime } from '@/features/jobs/lib/format';
import { index as applicationsIndex } from '@/routes/applications';
import {
    applied,
    approve,
    destroy,
    letter,
    resume as resumePdf,
    resumePreview,
    retry,
    show,
    update,
} from '@/routes/applications/packages';
import { show as showJob } from '@/routes/jobs';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    ExternalLink,
    Eye,
    FileDown,
    Send,
    Trash2,
} from '@lucide/vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    package: ApplicationPackage;
    job: {
        id: number;
        slug: string;
        title: string;
        company: string;
        apply_url: string;
        is_open: boolean;
    };
    missingKeywords: string[];
    history: { id: number; status: string; created_at: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.applications', href: applicationsIndex() }],
    },
});

const copy = <T,>(value: T): T => JSON.parse(JSON.stringify(value)) as T;

const form = useForm<{ cover_letter: string; resume: Resume | null }>({
    cover_letter: props.package.cover_letter ?? '',
    resume: props.package.resume ? copy(props.package.resume) : null,
});

// When generation finishes (polling) or the server saves, start from its version.
watch(
    () => [
        props.package.status,
        props.package.cover_letter,
        props.package.resume,
    ],
    () => {
        form.defaults({
            cover_letter: props.package.cover_letter ?? '',
            resume: props.package.resume ? copy(props.package.resume) : null,
        });
        form.reset();
    },
);

const generating = computed(() => props.package.status === 'generating');
const { start, stop } = usePoll(
    2500,
    { only: ['package'] },
    { autoStart: false },
);
watch(generating, (value) => (value ? start() : stop()), { immediate: true });

const templates = ['classic', 'modern', 'compact'] as const;
const template = ref<(typeof templates)[number]>('classic');

const previewOpen = ref(false);
// A new query string each time it opens, so the browser never shows a stale PDF.
const previewVersion = ref(0);
const previewUrl = computed(
    () =>
        `${resumePreview.url(props.package.id, { query: { template: template.value } })}&v=${previewVersion.value}#toolbar=0&navpanes=0`,
);
const openPreview = () => {
    previewVersion.value = Date.now();
    previewOpen.value = true;
};
const approved = computed(() => props.package.status === 'approved');
const editable = computed(() =>
    ['draft', 'approved'].includes(props.package.status),
);
const created = computed(() =>
    relativeTime(props.package.created_at, getActiveLanguage()),
);

function warning(item: PackageWarning): string {
    const params = { ...item.params };
    if (item.key === 'ai_unavailable' && params.reason) {
        params.reason = trans(`ai.errors.${params.reason}`);
    }

    return trans(`quick_apply.warnings.${item.key}`, params);
}

function save(): void {
    form.put(update.url(props.package.id), { preserveScroll: true });
}

function approvePackage(): void {
    const send = () =>
        router.post(
            approve.url(props.package.id),
            {},
            { preserveScroll: true },
        );
    // Unsaved edits are saved first: what is approved is what the person sees.
    if (form.isDirty) {
        form.put(update.url(props.package.id), {
            preserveScroll: true,
            onSuccess: send,
        });
    } else {
        send();
    }
}

function remove(): void {
    if (window.confirm(trans('quick_apply.package.delete_confirm'))) {
        router.delete(destroy.url(props.package.id));
    }
}
</script>

<template>
    <Head :title="trans('quick_apply.package.title', { title: job.title })" />

    <div class="mx-auto w-full max-w-4xl space-y-8 px-4 py-6">
        <header class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{
                        trans('quick_apply.package.title', { title: job.title })
                    }}
                </h1>
                <Badge :variant="approved ? 'default' : 'outline'">{{
                    trans(`quick_apply.package.status.${package.status}`)
                }}</Badge>
            </div>
            <p class="text-sm text-muted-foreground">
                <Link
                    :href="showJob({ job: job.id, slug: job.slug })"
                    class="underline-offset-2 hover:underline"
                    >{{ job.company }}</Link
                >
                ·
                {{
                    trans('quick_apply.package.prepared_at', { time: created })
                }}
            </p>
            <!-- Always reachable, whatever the state of the package. -->
            <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                <Link
                    :href="showJob({ job: job.id, slug: job.slug })"
                    class="font-medium underline underline-offset-2"
                    >{{ trans('quick_apply.package.view_job') }}</Link
                >
                <a
                    v-if="job.is_open"
                    :href="job.apply_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 font-medium underline underline-offset-2"
                    >{{ trans('quick_apply.package.open_original')
                    }}<ExternalLink class="size-3.5" aria-hidden="true"
                /></a>
            </p>
        </header>

        <div
            v-if="generating"
            class="flex items-center gap-3 rounded-xl border p-6"
            role="status"
            aria-live="polite"
        >
            <Spinner class="size-5" />
            {{ trans('quick_apply.package.generating') }}
        </div>

        <div
            v-else-if="package.status === 'failed'"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-destructive/40 p-4 text-sm"
            role="alert"
        >
            <span class="flex items-center gap-2">
                <CircleAlert
                    class="size-4 text-destructive"
                    aria-hidden="true"
                />
                {{ trans('quick_apply.package.failed') }}
            </span>
            <Button size="sm" @click="router.post(retry.url(package.id))">{{
                trans('quick_apply.package.retry')
            }}</Button>
        </div>

        <template v-else-if="editable && form.resume">
            <div
                class="rounded-xl border p-4 text-sm"
                :class="
                    approved
                        ? 'border-emerald-500/40 bg-emerald-500/5'
                        : 'bg-muted/40'
                "
            >
                <p class="flex items-start gap-2">
                    <CircleCheck
                        v-if="approved"
                        class="mt-0.5 size-4 shrink-0 text-emerald-600"
                        aria-hidden="true"
                    />
                    {{
                        approved
                            ? trans('quick_apply.package.approved_notice')
                            : trans('quick_apply.package.review_notice')
                    }}
                </p>
            </div>

            <section
                v-if="package.warnings.length"
                class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 text-sm"
                aria-labelledby="warnings-title"
            >
                <h2
                    id="warnings-title"
                    class="mb-1 flex items-center gap-2 font-medium"
                >
                    <CircleAlert
                        class="size-4 text-amber-600"
                        aria-hidden="true"
                    />
                    {{ trans('quick_apply.warnings.title') }}
                </h2>
                <ul class="list-disc space-y-1 pl-6">
                    <li v-for="(item, index) in package.warnings" :key="index">
                        {{ warning(item) }}
                    </li>
                </ul>
            </section>

            <div class="flex flex-wrap gap-2">
                <Button v-if="!approved" @click="approvePackage">
                    <CircleCheck class="size-4" aria-hidden="true" />
                    {{ trans('quick_apply.package.approve') }}
                </Button>
                <Button
                    v-if="form.isDirty"
                    variant="outline"
                    :disabled="form.processing"
                    @click="save"
                >
                    {{ trans('quick_apply.package.save') }}
                </Button>
                <div v-if="approved" class="flex items-center gap-2">
                    <Label for="resume-template" class="text-sm">{{
                        trans('quick_apply.template.label')
                    }}</Label>
                    <NativeSelect
                        id="resume-template"
                        v-model="template"
                        class="h-9 w-auto text-sm"
                    >
                        <option
                            v-for="option in templates"
                            :key="option"
                            :value="option"
                        >
                            {{ trans(`quick_apply.template.${option}`) }}
                        </option>
                    </NativeSelect>
                </div>
                <Button variant="outline" @click="openPreview">
                    <Eye class="size-4" aria-hidden="true" />{{
                        trans('quick_apply.preview.open')
                    }}
                </Button>
                <Button v-if="approved" variant="outline" as-child>
                    <a
                        :href="
                            resumePdf.url(package.id, {
                                query: { template },
                            })
                        "
                        ><FileDown class="size-4" aria-hidden="true" />{{
                            trans('quick_apply.download_resume')
                        }}</a
                    >
                </Button>
                <!-- The person applies themselves on the employer's site; nothing is sent from here. -->
                <Button v-if="job.is_open" variant="outline" as-child>
                    <a
                        :href="job.apply_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        ><ExternalLink class="size-4" aria-hidden="true" />{{
                            trans('quick_apply.package.open_job')
                        }}</a
                    >
                </Button>
                <Button
                    v-if="approved"
                    variant="ghost"
                    @click="
                        router.post(
                            applied.url(package.id),
                            {},
                            { preserveScroll: true },
                        )
                    "
                >
                    <Send class="size-4" aria-hidden="true" />
                    {{ trans('quick_apply.package.mark_applied') }}
                </Button>
            </div>
            <p v-if="!approved" class="-mt-4 text-xs text-muted-foreground">
                {{ trans('quick_apply.download_requires_approval') }}
            </p>

            <section class="space-y-3" aria-labelledby="letter-title">
                <h2 id="letter-title" class="text-base font-medium">
                    {{ trans('quick_apply.sections.letter') }}
                </h2>
                <CoverLetterEditor
                    v-model="form.cover_letter"
                    :source="package.letter_source"
                    :download-url="letter.url(package.id)"
                />
            </section>

            <section class="space-y-3" aria-labelledby="resume-title">
                <h2 id="resume-title" class="text-base font-medium">
                    {{ trans('quick_apply.sections.resume') }}
                </h2>
                <CvEditor
                    v-model="form.resume"
                    :master="package.master_resume"
                    :source="package.resume_source"
                    :missing-keywords="missingKeywords"
                />
            </section>

            <section class="space-y-3" aria-labelledby="contact-title">
                <h2 id="contact-title" class="text-base font-medium">
                    {{ trans('quick_apply.sections.contact') }}
                </h2>
                <ContactCard :contact="form.resume.contact" />
            </section>
        </template>

        <section
            v-if="history.length"
            class="space-y-2"
            aria-labelledby="history-title"
        >
            <h2 id="history-title" class="text-sm font-medium">
                {{ trans('quick_apply.package.history') }}
            </h2>
            <ul class="divide-y rounded-lg border text-sm">
                <li
                    v-for="item in history"
                    :key="item.id"
                    class="flex items-center justify-between px-3 py-2"
                >
                    <Link
                        :href="show(item.id)"
                        class="underline-offset-2 hover:underline"
                        >{{
                            relativeTime(item.created_at, getActiveLanguage())
                        }}</Link
                    >
                    <Badge variant="outline">{{
                        trans(`quick_apply.package.status.${item.status}`)
                    }}</Badge>
                </li>
            </ul>
        </section>

        <div class="border-t pt-4">
            <Button
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive"
                @click="remove"
            >
                <Trash2 class="size-4" aria-hidden="true" />
                {{ trans('quick_apply.package.delete') }}
            </Button>
        </div>
    </div>

    <Dialog v-model:open="previewOpen">
        <DialogContent
            class="flex h-[90vh] w-[calc(100vw-1.5rem)] max-w-4xl flex-col gap-3 sm:max-w-4xl"
        >
            <DialogHeader>
                <DialogTitle>{{
                    trans('quick_apply.preview.title')
                }}</DialogTitle>
                <DialogDescription>{{
                    trans('quick_apply.preview.hint')
                }}</DialogDescription>
            </DialogHeader>
            <iframe
                v-if="previewOpen"
                :src="previewUrl"
                :title="trans('quick_apply.preview.title')"
                class="min-h-0 w-full flex-1 rounded-md border bg-white"
            />
        </DialogContent>
    </Dialog>
</template>
