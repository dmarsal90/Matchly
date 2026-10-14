<script setup lang="ts">
import { Button } from '@/components/ui/button';
import LanguagesField from '@/features/profile/components/LanguagesField.vue';
import ConditionsStep from '@/features/profile/components/questionnaire/ConditionsStep.vue';
import LocationStep from '@/features/profile/components/questionnaire/LocationStep.vue';
import RoleStep from '@/features/profile/components/questionnaire/RoleStep.vue';
import SkillsStep from '@/features/profile/components/questionnaire/SkillsStep.vue';
import type {
    QuestionnaireAnswers,
    QuestionnaireOptions,
    QuestionnaireStep,
} from '@/features/profile/types';
import { show, update } from '@/routes/onboarding';
import { index as skillsIndex } from '@/routes/skills';
import { Link, useForm } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    step: QuestionnaireStep;
    steps: QuestionnaireStep[];
    answers: QuestionnaireAnswers;
    options: QuestionnaireOptions;
}>();

/** Fields sent by each step: every step is validated and saved on its own. */
const FIELDS: Record<QuestionnaireStep, (keyof QuestionnaireAnswers)[]> = {
    role: ['target_roles', 'seniority', 'years_experience'],
    skills: ['skills'],
    languages: ['languages'],
    location: [
        'regions',
        'utc_offset_min',
        'utc_offset_max',
        'work_modes',
        'employment_types',
    ],
    conditions: [
        'salary_min',
        'salary_currency',
        'salary_period',
        'dealbreakers',
        'dealbreaker_notes',
    ],
};

const COMPONENTS = {
    role: RoleStep,
    skills: SkillsStep,
    location: LocationStep,
    conditions: ConditionsStep,
} as const;

// Props are reactive proxies; the form needs a plain copy it can own.
const form = useForm<QuestionnaireAnswers>(
    JSON.parse(JSON.stringify(props.answers)) as QuestionnaireAnswers,
);

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const index = computed(() => props.steps.indexOf(props.step));
const previous = computed(() => props.steps[index.value - 1] ?? null);
const isLast = computed(() => index.value === props.steps.length - 1);

function submit(): void {
    form.transform((data) =>
        Object.fromEntries(
            FIELDS[props.step].map((field) => [field, data[field]]),
        ),
    ).put(update.url(props.step), { preserveScroll: false });
}
</script>

<template>
    <div class="space-y-8">
        <nav :aria-label="trans('onboarding.steps_label')">
            <ol class="grid grid-cols-5 gap-2">
                <li v-for="(name, position) in steps" :key="name">
                    <Link
                        :href="show({ query: { step: name } })"
                        class="group flex flex-col gap-2 text-xs font-medium focus-visible:outline-none"
                        :aria-current="name === step ? 'step' : undefined"
                    >
                        <span
                            class="h-1.5 rounded-full transition-colors group-focus-visible:ring-2 group-focus-visible:ring-ring"
                            :class="
                                position <= index ? 'bg-primary' : 'bg-muted'
                            "
                        />
                        <span
                            class="hidden items-center gap-1 sm:flex"
                            :class="
                                name === step
                                    ? 'text-foreground'
                                    : 'text-muted-foreground'
                            "
                        >
                            <Check
                                v-if="position < index"
                                class="size-3"
                                aria-hidden="true"
                            />
                            {{ trans(`onboarding.steps.${name}.title`) }}
                        </span>
                        <span class="sr-only sm:hidden">{{
                            trans(`onboarding.steps.${name}.title`)
                        }}</span>
                    </Link>
                </li>
            </ol>
        </nav>

        <form class="space-y-8" novalidate @submit.prevent="submit">
            <header class="space-y-1">
                <p class="text-xs font-medium text-muted-foreground">
                    {{
                        trans('onboarding.step_of', {
                            current: String(index + 1),
                            total: String(steps.length),
                        })
                    }}
                </p>
                <h2 class="text-lg font-semibold">
                    {{ trans(`onboarding.steps.${step}.title`) }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ trans(`onboarding.steps.${step}.description`) }}
                </p>
            </header>

            <div v-if="step === 'languages'" class="space-y-4">
                <p class="text-sm text-muted-foreground">
                    {{ trans('onboarding.languages.intro') }}
                </p>
                <LanguagesField
                    v-model="form.languages"
                    :levels="options.languageLevels"
                    :errors="errors"
                />
            </div>
            <component
                :is="COMPONENTS[step]"
                v-else
                :answers="form"
                :options="options"
                :errors="errors"
            />

            <div
                class="flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex gap-2">
                    <Button v-if="previous" variant="ghost" as-child>
                        <Link :href="show({ query: { step: previous } })">{{
                            trans('onboarding.back')
                        }}</Link>
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link :href="skillsIndex()">{{
                            trans('onboarding.later')
                        }}</Link>
                    </Button>
                </div>
                <Button type="submit" :disabled="form.processing">
                    {{
                        isLast
                            ? trans('onboarding.finish')
                            : trans('onboarding.save_and_continue')
                    }}
                </Button>
            </div>
        </form>
    </div>
</template>
