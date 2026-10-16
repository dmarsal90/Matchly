<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { PracticeAnswer } from '@/features/applications/types';
import {
    answer as answerRoute,
    feedback as feedbackRoute,
} from '@/routes/applications/interview-prep';
import { useForm } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    applicationId: number;
    questionKey: string;
    saved: PracticeAnswer | undefined;
    aiAvailable: boolean;
    aiConsented: boolean;
}>();

const MAX = 4000;

const answerForm = useForm({
    key: props.questionKey,
    answer: props.saved?.answer ?? '',
});
const feedbackForm = useForm({
    key: props.questionKey,
    use_ai: false,
    ai_consent: false,
});

const dirty = computed(
    () => answerForm.answer.trim() !== (props.saved?.answer ?? ''),
);
const needsConsent = computed(() => feedbackForm.use_ai && !props.aiConsented);
const id = (name: string): string => `${name}-${props.questionKey}`;

function save(): void {
    answerForm.put(answerRoute.url(props.applicationId), {
        preserveScroll: true,
    });
}

function askFeedback(): void {
    feedbackForm.post(feedbackRoute.url(props.applicationId), {
        preserveScroll: true,
    });
}

function note(item: { key?: string; text?: string }): string {
    return item.text ?? trans(`interview_prep.practice.checks.${item.key}`);
}
</script>

<template>
    <details class="rounded-lg bg-muted/30 p-3">
        <summary class="cursor-pointer text-sm font-medium">
            {{ trans('interview_prep.practice.title') }}
            <span v-if="saved" class="font-normal text-muted-foreground"
                >· {{ trans('interview_prep.practice.saved') }}</span
            >
        </summary>

        <div class="mt-3 grid gap-3">
            <div class="grid gap-1.5">
                <Label :for="id('answer')" class="text-xs">{{
                    trans('interview_prep.practice.label')
                }}</Label>
                <textarea
                    :id="id('answer')"
                    v-model="answerForm.answer"
                    rows="6"
                    :maxlength="MAX"
                    :placeholder="trans('interview_prep.practice.placeholder')"
                    class="w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="answerForm.errors.answer" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    size="sm"
                    :disabled="answerForm.processing || !dirty"
                    @click="save"
                    >{{ trans('interview_prep.practice.save') }}</Button
                >
            </div>

            <form
                v-if="saved && !dirty"
                class="grid gap-2 border-t pt-3"
                @submit.prevent="askFeedback"
            >
                <div v-if="aiAvailable" class="flex items-start gap-2">
                    <Checkbox
                        :id="id('ai')"
                        v-model="feedbackForm.use_ai"
                        class="mt-0.5"
                    />
                    <Label
                        :for="id('ai')"
                        class="flex items-center gap-1.5 text-sm font-normal"
                    >
                        <Sparkles class="size-3.5" aria-hidden="true" />
                        {{ trans('interview_prep.practice.use_ai') }}
                    </Label>
                </div>
                <div v-if="needsConsent" class="flex items-start gap-2">
                    <Checkbox
                        :id="id('consent')"
                        v-model="feedbackForm.ai_consent"
                        class="mt-0.5"
                    />
                    <Label
                        :for="id('consent')"
                        class="text-xs leading-snug font-normal text-muted-foreground"
                        >{{
                            trans('interview_prep.practice.ai_consent')
                        }}</Label
                    >
                </div>
                <InputError :message="feedbackForm.errors.ai_consent" />
                <div>
                    <Button
                        type="submit"
                        size="sm"
                        variant="secondary"
                        :disabled="feedbackForm.processing"
                        >{{ trans('interview_prep.practice.feedback') }}</Button
                    >
                </div>
            </form>

            <div
                v-if="saved && !dirty && saved.feedback"
                class="grid gap-2 text-sm"
                aria-live="polite"
            >
                <p class="text-xs text-muted-foreground">
                    {{
                        trans(
                            `interview_prep.practice.source.${saved.feedback_source}`,
                        )
                    }}
                </p>
                <div v-if="saved.feedback.strengths.length">
                    <p class="font-medium">
                        {{ trans('interview_prep.practice.strengths') }}
                    </p>
                    <ul class="list-disc space-y-0.5 pl-5">
                        <li
                            v-for="(item, index) in saved.feedback.strengths"
                            :key="index"
                        >
                            {{ note(item) }}
                        </li>
                    </ul>
                </div>
                <div v-if="saved.feedback.improvements.length">
                    <p class="font-medium">
                        {{ trans('interview_prep.practice.improvements') }}
                    </p>
                    <ul class="list-disc space-y-0.5 pl-5">
                        <li
                            v-for="(item, index) in saved.feedback.improvements"
                            :key="index"
                        >
                            {{ note(item) }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </details>
</template>
