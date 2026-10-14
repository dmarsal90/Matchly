<script setup lang="ts">
import CheckboxGroup from '@/components/CheckboxGroup.vue';
import InputError from '@/components/InputError.vue';
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

const OFFSETS = Array.from({ length: 27 }, (_, index) => index - 12);
const utcLabel = (offset: number): string =>
    offset === 0 ? 'UTC' : `UTC${offset > 0 ? '+' : '−'}${Math.abs(offset)}`;

const toOptions = (values: string[], prefix: string) =>
    values.map((value) => ({ value, label: trans(`${prefix}.${value}`) }));

const regionOptions = computed(() =>
    toOptions(props.options.regions, 'jobs.region'),
);
const workModeOptions = computed(() =>
    toOptions(props.options.workModes, 'profile.work_modes'),
);
const employmentOptions = computed(() =>
    toOptions(props.options.employmentTypes, 'profile.employment_types'),
);
</script>

<template>
    <div class="space-y-8">
        <div class="grid gap-1">
            <CheckboxGroup
                v-model="answers.regions"
                name="regions"
                :legend="trans('onboarding.location.regions')"
                :options="regionOptions"
            />
            <p class="text-xs text-muted-foreground">
                {{ trans('onboarding.location.regions_hint') }}
            </p>
            <InputError :message="errors.regions" />
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-1 text-sm font-medium">
                {{ trans('onboarding.location.timezone') }}
            </legend>
            <div class="grid max-w-md grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="utc-min" class="font-normal">{{
                        trans('onboarding.location.utc_from')
                    }}</Label>
                    <NativeSelect id="utc-min" v-model="answers.utc_offset_min">
                        <option :value="null">
                            {{ trans('onboarding.location.utc_any') }}
                        </option>
                        <option
                            v-for="offset in OFFSETS"
                            :key="offset"
                            :value="offset"
                        >
                            {{ utcLabel(offset) }}
                        </option>
                    </NativeSelect>
                </div>
                <div class="grid gap-1.5">
                    <Label for="utc-max" class="font-normal">{{
                        trans('onboarding.location.utc_to')
                    }}</Label>
                    <NativeSelect id="utc-max" v-model="answers.utc_offset_max">
                        <option :value="null">
                            {{ trans('onboarding.location.utc_any') }}
                        </option>
                        <option
                            v-for="offset in OFFSETS"
                            :key="offset"
                            :value="offset"
                        >
                            {{ utcLabel(offset) }}
                        </option>
                    </NativeSelect>
                </div>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ trans('onboarding.location.timezone_hint') }}
            </p>
            <InputError
                :message="errors.utc_offset_min ?? errors.utc_offset_max"
            />
        </fieldset>

        <CheckboxGroup
            v-model="answers.work_modes"
            name="work-modes"
            :legend="trans('onboarding.location.work_modes')"
            :options="workModeOptions"
        />

        <CheckboxGroup
            v-model="answers.employment_types"
            name="employment-types"
            :legend="trans('onboarding.location.employment_types')"
            :options="employmentOptions"
        />
    </div>
</template>
