<script setup lang="ts">
import CheckboxGroup from '@/components/CheckboxGroup.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import type {
    QuestionnaireAnswers,
    QuestionnaireOptions,
} from '@/features/profile/types';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    options: QuestionnaireOptions;
    errors: Record<string, string | undefined>;
}>();

const answers = defineModel<QuestionnaireAnswers>('answers', {
    required: true,
});

const roleOptions = computed(() =>
    props.options.roles.map((value) => ({
        value,
        label: trans(`jobs.role.${value}`),
    })),
);
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-1">
            <CheckboxGroup
                v-model="answers.target_roles"
                name="target-roles"
                :legend="trans('onboarding.role.target_roles')"
                :options="roleOptions"
            />
            <p class="text-xs text-muted-foreground">
                {{ trans('onboarding.role.target_roles_hint') }}
            </p>
            <InputError
                :message="errors.target_roles ?? errors['target_roles.0']"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="seniority">{{
                    trans('onboarding.role.seniority')
                }}</Label>
                <NativeSelect
                    id="seniority"
                    v-model="answers.seniority"
                    required
                >
                    <option :value="null" disabled>
                        {{ trans('onboarding.role.seniority_placeholder') }}
                    </option>
                    <option
                        v-for="value in options.seniorities"
                        :key="value"
                        :value="value"
                    >
                        {{ trans(`jobs.seniority.${value}`) }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.seniority" />
            </div>
            <div class="grid gap-2">
                <Label for="years-experience">{{
                    trans('onboarding.role.years_experience')
                }}</Label>
                <Input
                    id="years-experience"
                    v-model.number="answers.years_experience"
                    type="number"
                    min="0"
                    max="50"
                    inputmode="numeric"
                    required
                />
                <InputError :message="errors.years_experience" />
            </div>
        </div>
    </div>
</template>
