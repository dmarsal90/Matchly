<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import SkillsQuestionnaire from '@/features/profile/components/SkillsQuestionnaire.vue';
import type {
    QuestionnaireAnswers,
    QuestionnaireOptions,
    QuestionnaireStep,
} from '@/features/profile/types';
import { show } from '@/routes/onboarding';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    step: QuestionnaireStep;
    steps: QuestionnaireStep[];
    completed: boolean;
    answers: QuestionnaireAnswers;
    options: QuestionnaireOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'onboarding.title', href: show() }],
    },
});
</script>

<template>
    <Head :title="trans('onboarding.title')" />

    <div class="mx-auto w-full max-w-3xl space-y-8 px-4 py-6">
        <Heading
            :title="trans('onboarding.title')"
            :description="trans('onboarding.description')"
        />

        <p
            class="rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground"
        >
            {{ trans('onboarding.fairness') }}
        </p>

        <!-- Keyed by step: each step starts from the saved answers. -->
        <SkillsQuestionnaire
            :key="step"
            :step="step"
            :steps="steps"
            :answers="answers"
            :options="options"
        />
    </div>
</template>
