<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type {
    PracticeAnswer,
    PracticeNote,
} from '@/features/applications/types';
import { index as applicationsIndex } from '@/routes/applications';
import {
    answer as answerRoute,
    mock as mockRoute,
    show as prepRoute,
} from '@/routes/applications/interview-prep';
import { review as reviewRoute } from '@/routes/applications/interview-prep/mock';
import { Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

const props = defineProps<{
    application: { id: number };
    job: { title: string; company: string };
    questions: { key: string; category: string; question: string }[];
    answers: Record<string, PracticeAnswer>;
    secondsPerAnswer: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.applications', href: applicationsIndex() }],
    },
});

const phase = ref<'intro' | 'running' | 'summary'>('intro');
const index = ref(0);
const saving = ref(false);
const drafts = reactive<Record<string, string>>(
    Object.fromEntries(
        props.questions.map((q) => [q.key, props.answers[q.key]?.answer ?? '']),
    ),
);

const current = computed(() => props.questions[index.value]);
const last = computed(() => index.value === props.questions.length - 1);

// A guide, not a limit: it counts down and then keeps the answer open.
const remaining = ref(props.secondsPerAnswer);
let timer: ReturnType<typeof setInterval> | null = null;
function startTimer(): void {
    stopTimer();
    remaining.value = props.secondsPerAnswer;
    timer = setInterval(
        () => (remaining.value = Math.max(0, remaining.value - 1)),
        1000,
    );
}
function stopTimer(): void {
    if (timer !== null) clearInterval(timer);
    timer = null;
}
onBeforeUnmount(stopTimer);

const clock = computed(
    () =>
        `${Math.floor(remaining.value / 60)}:${String(remaining.value % 60).padStart(2, '0')}`,
);

function begin(): void {
    phase.value = 'running';
    index.value = 0;
    startTimer();
}

function saveCurrent(onDone: () => void): void {
    saving.value = true;
    router.put(
        answerRoute.url(props.application.id),
        { key: current.value.key, answer: drafts[current.value.key] },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['answers'],
            onFinish: () => (saving.value = false),
            onSuccess: onDone,
        },
    );
}

function next(): void {
    saveCurrent(() => {
        index.value++;
        startTimer();
    });
}

function finish(): void {
    saveCurrent(() => {
        stopTimer();
        router.post(
            reviewRoute.url(props.application.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['answers'],
                onSuccess: () => (phase.value = 'summary'),
            },
        );
    });
}

function note(item: PracticeNote): string {
    return item.text ?? trans(`interview_prep.practice.checks.${item.key}`);
}

const results = computed(() =>
    props.questions
        .map((q) => {
            const saved = props.answers[q.key];

            return {
                ...q,
                answered: !!saved,
                improvements: saved?.feedback?.improvements ?? [],
                strengths: saved?.feedback?.strengths ?? [],
            };
        })
        // Weakest first: unanswered, then the most things to improve.
        .sort(
            (a, b) =>
                Number(a.answered) - Number(b.answered) ||
                b.improvements.length - a.improvements.length,
        ),
);
const answeredCount = computed(
    () => results.value.filter((r) => r.answered).length,
);
</script>

<template>
    <Head :title="trans('interview_prep.mock.title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6">
        <Heading
            :title="trans('interview_prep.mock.title')"
            :description="
                trans('interview_prep.mock.description', {
                    title: job.title,
                    company: job.company,
                })
            "
        />

        <section v-if="phase === 'intro'" class="space-y-3">
            <p class="text-sm">
                {{
                    trans('interview_prep.mock.intro', {
                        count: String(questions.length),
                        minutes: String(Math.round(secondsPerAnswer / 60)),
                    })
                }}
            </p>
            <div class="flex flex-wrap gap-2">
                <Button @click="begin">{{
                    trans('interview_prep.mock.start')
                }}</Button>
                <Button variant="outline" as-child>
                    <Link :href="prepRoute({ application: application.id })">{{
                        trans('interview_prep.mock.back')
                    }}</Link>
                </Button>
            </div>
        </section>

        <section
            v-else-if="phase === 'running' && current"
            class="space-y-4"
            aria-labelledby="mock-question"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-muted-foreground">
                    {{
                        trans('interview_prep.mock.progress', {
                            current: String(index + 1),
                            total: String(questions.length),
                        })
                    }}
                </p>
                <p
                    role="timer"
                    class="text-sm tabular-nums"
                    :class="remaining === 0 ? 'text-amber-600' : ''"
                >
                    {{ clock }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{
                    trans(
                        `interview_prep.questions.categories.${current.category}`,
                    )
                }}</Badge>
                <h2 id="mock-question" class="text-lg font-semibold">
                    {{ current.question }}
                </h2>
            </div>
            <div class="grid gap-1.5">
                <Label for="mock-answer" class="text-xs">{{
                    trans('interview_prep.practice.label')
                }}</Label>
                <textarea
                    id="mock-answer"
                    :key="current.key"
                    v-model="drafts[current.key]"
                    rows="8"
                    maxlength="4000"
                    :placeholder="trans('interview_prep.practice.placeholder')"
                    class="w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="!last" :disabled="saving" @click="next">{{
                    trans('interview_prep.mock.next')
                }}</Button>
                <Button v-else :disabled="saving" @click="finish">{{
                    trans('interview_prep.mock.finish')
                }}</Button>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ trans('interview_prep.mock.skip_hint') }}
            </p>
        </section>

        <section v-else-if="phase === 'summary'" class="space-y-4">
            <p class="text-sm" role="status">
                {{
                    trans('interview_prep.mock.summary', {
                        answered: String(answeredCount),
                        total: String(questions.length),
                    })
                }}
            </p>
            <article
                v-for="item in results"
                :key="item.key"
                class="space-y-2 rounded-xl border p-4"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <Badge :variant="item.answered ? 'secondary' : 'outline'">{{
                        item.answered
                            ? trans('interview_prep.mock.answered')
                            : trans('interview_prep.mock.skipped')
                    }}</Badge>
                    <h3 class="font-medium">{{ item.question }}</h3>
                </div>
                <ul
                    v-if="item.improvements.length"
                    class="list-disc space-y-0.5 pl-5 text-sm"
                >
                    <li v-for="(entry, i) in item.improvements" :key="i">
                        {{ note(entry) }}
                    </li>
                </ul>
                <p
                    v-else-if="item.answered"
                    class="text-sm text-muted-foreground"
                >
                    {{ trans('interview_prep.mock.nothing_to_improve') }}
                </p>
            </article>
            <div class="flex flex-wrap gap-2">
                <Button as-child>
                    <Link :href="mockRoute({ application: application.id })">{{
                        trans('interview_prep.mock.again')
                    }}</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="prepRoute({ application: application.id })">{{
                        trans('interview_prep.mock.back')
                    }}</Link>
                </Button>
            </div>
        </section>
    </div>
</template>
