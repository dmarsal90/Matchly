<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import type {
    QuestionnaireAnswers,
    QuestionnaireOptions,
} from '@/features/profile/types';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    options: QuestionnaireOptions;
    errors: Record<string, string | undefined>;
}>();

const answers = defineModel<QuestionnaireAnswers>('answers', {
    required: true,
});

function toggle(value: string, checked: boolean | 'indeterminate'): void {
    answers.value.dealbreakers =
        checked === true
            ? [...new Set([...answers.value.dealbreakers, value])]
            : answers.value.dealbreakers.filter((item) => item !== value);
}
</script>

<template>
    <div class="space-y-8">
        <fieldset class="grid gap-2">
            <legend class="mb-1 text-sm font-medium">
                {{ trans('onboarding.conditions.salary') }}
            </legend>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <Label for="salary-min" class="font-normal">{{
                        trans('profile.fields.salary_min')
                    }}</Label>
                    <Input
                        id="salary-min"
                        v-model.number="answers.salary_min"
                        type="number"
                        min="0"
                        inputmode="numeric"
                    />
                    <InputError :message="errors.salary_min" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="salary-currency" class="font-normal">{{
                        trans('profile.fields.salary_currency')
                    }}</Label>
                    <NativeSelect
                        id="salary-currency"
                        v-model="answers.salary_currency"
                    >
                        <option :value="null">—</option>
                        <option
                            v-for="currency in options.currencies"
                            :key="currency"
                            :value="currency"
                        >
                            {{ currency }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.salary_currency" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="salary-period" class="font-normal">{{
                        trans('profile.fields.salary_period')
                    }}</Label>
                    <NativeSelect
                        id="salary-period"
                        v-model="answers.salary_period"
                    >
                        <option :value="null">—</option>
                        <option
                            v-for="period in options.salaryPeriods"
                            :key="period"
                            :value="period"
                        >
                            {{ trans(`profile.periods.${period}`) }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.salary_period" />
                </div>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ trans('onboarding.conditions.salary_hint') }}
            </p>
        </fieldset>

        <fieldset class="grid gap-3">
            <legend class="mb-1 text-sm font-medium">
                {{ trans('onboarding.conditions.dealbreakers') }}
            </legend>
            <p class="-mt-2 text-xs text-muted-foreground">
                {{ trans('onboarding.conditions.dealbreakers_hint') }}
            </p>
            <div
                v-for="value in options.dealbreakers"
                :key="value"
                class="flex items-center gap-2"
            >
                <Checkbox
                    :id="`dealbreaker-${value}`"
                    :model-value="answers.dealbreakers.includes(value)"
                    @update:model-value="toggle(value, $event)"
                />
                <Label :for="`dealbreaker-${value}`" class="font-normal">{{
                    trans(`onboarding.conditions.options.${value}`)
                }}</Label>
            </div>
            <InputError :message="errors.dealbreakers" />
        </fieldset>

        <div class="grid gap-2">
            <Label for="dealbreaker-notes">{{
                trans('onboarding.conditions.notes')
            }}</Label>
            <textarea
                id="dealbreaker-notes"
                v-model="answers.dealbreaker_notes"
                rows="3"
                maxlength="500"
                aria-describedby="dealbreaker-notes-hint"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
            />
            <p
                id="dealbreaker-notes-hint"
                class="text-xs text-muted-foreground"
            >
                {{ trans('onboarding.conditions.notes_hint') }}
            </p>
            <InputError :message="errors.dealbreaker_notes" />
        </div>
    </div>
</template>
