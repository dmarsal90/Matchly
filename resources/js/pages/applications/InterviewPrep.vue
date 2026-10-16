<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import InterviewPractice from '@/features/applications/components/InterviewPractice.vue';
import type {
    InterviewPrepData,
    PackageWarning,
    PracticeAnswer,
} from '@/features/applications/types';
import { index as applicationsIndex } from '@/routes/applications';
import { mock, store } from '@/routes/applications/interview-prep';
import { show as showJob } from '@/routes/jobs';
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import { CircleAlert, Sparkles } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';

const props = defineProps<{
    application: { id: number; status: string };
    job: { id: number; slug: string; title: string; company: string };
    prep: InterviewPrepData | null;
    answers: Record<string, PracticeAnswer>;
    options: { ai_available: boolean; ai_consented: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.applications', href: applicationsIndex() }],
    },
});

const form = useForm({
    use_ai: props.options.ai_available && props.options.ai_consented,
    ai_consent: false,
});
const needsConsent = computed(() => form.use_ai && !props.options.ai_consented);

const generating = computed(() => props.prep?.status === 'generating');
const { start, stop } = usePoll(2500, { only: ['prep'] }, { autoStart: false });
watch(generating, (value) => (value ? start() : stop()), { immediate: true });

function generate(): void {
    form.post(store.url(props.application.id), { preserveScroll: true });
}

function warning(item: PackageWarning): string {
    const params = { ...item.params };
    if (item.key === 'ai_unavailable' && params.reason) {
        params.reason = trans(`ai.errors.${params.reason}`);
    }
    if (item.key === 'star_removed') {
        return transChoice(
            'interview_prep.warnings.star_removed',
            Number(params.count),
            params,
        );
    }

    return trans(`interview_prep.warnings.${item.key}`, params);
}

const STAR = ['situation', 'task', 'action', 'result'] as const;
</script>

<template>
    <Head :title="trans('interview_prep.title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6">
        <Heading
            :title="trans('interview_prep.title')"
            :description="
                trans('interview_prep.description', {
                    title: job.title,
                    company: job.company,
                })
            "
        />
        <p class="text-sm">
            <Link
                :href="showJob({ job: job.id, slug: job.slug })"
                class="underline underline-offset-2"
                >{{ job.title }} · {{ job.company }}</Link
            >
            ·
            <Link
                :href="applicationsIndex()"
                class="underline underline-offset-2"
                >{{ trans('interview_prep.back') }}</Link
            >
        </p>

        <p
            v-if="generating"
            class="flex items-center gap-2 text-sm text-muted-foreground"
            role="status"
        >
            <Spinner /> {{ trans('interview_prep.generating') }}
        </p>
        <p
            v-else-if="prep?.status === 'failed'"
            class="text-sm text-destructive"
            role="alert"
        >
            {{ trans('interview_prep.failed') }}
        </p>

        <form
            v-if="!generating"
            class="space-y-3 rounded-xl border p-4"
            @submit.prevent="generate"
        >
            <h2 class="font-semibold">
                {{ trans('interview_prep.start.title') }}
            </h2>
            <p v-if="!prep" class="text-sm text-muted-foreground">
                {{ trans('interview_prep.start.description') }}
            </p>
            <div v-if="options.ai_available" class="flex items-start gap-2">
                <Checkbox id="prep-ai" v-model="form.use_ai" class="mt-0.5" />
                <Label
                    for="prep-ai"
                    class="flex items-center gap-1.5 font-normal"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    {{ trans('interview_prep.start.use_ai') }}
                </Label>
            </div>
            <div v-if="needsConsent" class="flex items-start gap-2">
                <Checkbox
                    id="prep-ai-consent"
                    v-model="form.ai_consent"
                    class="mt-0.5"
                />
                <Label
                    for="prep-ai-consent"
                    class="text-xs leading-snug font-normal text-muted-foreground"
                    >{{ trans('interview_prep.start.ai_consent') }}</Label
                >
            </div>
            <InputError :message="form.errors.ai_consent" />
            <p v-if="!form.use_ai" class="text-xs text-muted-foreground">
                {{ trans('interview_prep.start.no_ai_hint') }}
            </p>
            <Button type="submit" :disabled="form.processing">{{
                prep
                    ? trans('interview_prep.start.regenerate')
                    : trans('interview_prep.start.submit')
            }}</Button>
        </form>

        <template v-if="prep?.status === 'ready' && prep.content">
            <p class="text-xs text-muted-foreground">
                {{ trans(`interview_prep.source.${prep.source}`) }}
            </p>
            <ul v-if="prep.warnings.length" class="space-y-1">
                <li
                    v-for="item in prep.warnings"
                    :key="item.key"
                    class="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-sm"
                >
                    <CircleAlert
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{ warning(item) }}
                </li>
            </ul>

            <section class="space-y-3" aria-labelledby="prep-questions">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="prep-questions" class="text-lg font-semibold">
                        {{ trans('interview_prep.questions.title') }}
                    </h2>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="mock({ application: application.id })">{{
                            trans('interview_prep.mock.open')
                        }}</Link>
                    </Button>
                </div>
                <article
                    v-for="(item, index) in prep.content.questions"
                    :key="index"
                    class="space-y-2 rounded-xl border p-4"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">{{
                            trans(
                                `interview_prep.questions.categories.${item.category}`,
                            )
                        }}</Badge>
                        <h3 class="font-medium">{{ item.question }}</h3>
                    </div>
                    <details v-if="item.star">
                        <summary
                            class="cursor-pointer text-sm text-muted-foreground"
                        >
                            {{ trans('interview_prep.questions.star_title') }}
                        </summary>
                        <dl class="mt-2 grid gap-2 text-sm">
                            <div v-for="part in STAR" :key="part">
                                <dt class="font-medium">
                                    {{
                                        trans(
                                            `interview_prep.questions.${part}`,
                                        )
                                    }}
                                </dt>
                                <dd
                                    v-if="item.star[part]"
                                    class="whitespace-pre-line"
                                >
                                    {{ item.star[part] }}
                                </dd>
                                <dd v-else class="text-muted-foreground italic">
                                    {{
                                        trans(
                                            'interview_prep.questions.fill_in',
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </details>
                    <InterviewPractice
                        :application-id="application.id"
                        :question-key="item.key"
                        :saved="answers[item.key]"
                        :ai-available="options.ai_available"
                        :ai-consented="options.ai_consented"
                    />
                </article>
            </section>

            <section
                v-if="prep.content.gaps.length"
                class="space-y-3"
                aria-labelledby="prep-gaps"
            >
                <h2 id="prep-gaps" class="text-lg font-semibold">
                    {{ trans('interview_prep.gaps.title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ trans('interview_prep.gaps.description') }}
                </p>
                <article
                    v-for="gap in prep.content.gaps"
                    :key="gap.skill"
                    class="space-y-1 rounded-xl border p-4"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-medium">{{ gap.skill }}</h3>
                        <Badge variant="outline">{{
                            gap.required
                                ? trans('interview_prep.gaps.required')
                                : trans('interview_prep.gaps.nice')
                        }}</Badge>
                    </div>
                    <p class="text-sm whitespace-pre-line">{{ gap.answer }}</p>
                </article>
            </section>

            <section class="space-y-2" aria-labelledby="prep-ask">
                <h2 id="prep-ask" class="text-lg font-semibold">
                    {{ trans('interview_prep.ask.title') }}
                </h2>
                <ul class="list-disc space-y-1 pl-5 text-sm">
                    <li
                        v-for="(question, index) in prep.content.ask"
                        :key="index"
                    >
                        {{ question }}
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
