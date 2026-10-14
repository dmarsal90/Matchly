<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import type {
    QuestionnaireAnswers,
    QuestionnaireOptions,
} from '@/features/profile/types';
import { Plus, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    options: QuestionnaireOptions;
    errors: Record<string, string | undefined>;
}>();

const answers = defineModel<QuestionnaireAnswers>('answers', {
    required: true,
});

const LEVELS = [1, 2, 3, 4, 5];

// The current year reads as "currently"; older years go back fifteen years.
const years = computed(() =>
    Array.from({ length: 16 }, (_, index) => props.options.currentYear - index),
);

function add(): void {
    answers.value.skills = [
        ...answers.value.skills,
        { name: '', level: 3, years: null, last_used_year: null },
    ];
}

function remove(index: number): void {
    answers.value.skills = answers.value.skills.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
            {{ trans('onboarding.skills.intro') }}
        </p>

        <datalist id="skill-suggestions">
            <option
                v-for="suggestion in options.skillSuggestions"
                :key="suggestion"
                :value="suggestion"
            />
        </datalist>

        <p
            v-if="answers.skills.length === 0"
            class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
        >
            {{ trans('onboarding.skills.empty') }}
        </p>

        <ul class="space-y-3">
            <li
                v-for="(skill, index) in answers.skills"
                :key="index"
                class="grid grid-cols-2 items-start gap-2 rounded-lg border p-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1.4fr)_5rem_minmax(0,1.2fr)_auto] sm:border-0 sm:p-0"
            >
                <div class="col-span-2 grid gap-1.5 sm:col-span-1">
                    <Label
                        :for="`skill-${index}-name`"
                        :class="index > 0 && 'sm:sr-only'"
                        >{{ trans('onboarding.skills.name') }}</Label
                    >
                    <Input
                        :id="`skill-${index}-name`"
                        v-model="skill.name"
                        list="skill-suggestions"
                        maxlength="60"
                        autocomplete="off"
                        :placeholder="
                            trans('onboarding.skills.name_placeholder')
                        "
                        required
                    />
                    <InputError :message="errors[`skills.${index}.name`]" />
                </div>
                <div class="grid gap-1.5">
                    <Label
                        :for="`skill-${index}-level`"
                        :class="index > 0 && 'sm:sr-only'"
                        >{{ trans('onboarding.skills.level') }}</Label
                    >
                    <NativeSelect
                        :id="`skill-${index}-level`"
                        v-model="skill.level"
                    >
                        <option
                            v-for="level in LEVELS"
                            :key="level"
                            :value="level"
                        >
                            {{ trans(`onboarding.skills.levels.${level}`) }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors[`skills.${index}.level`]" />
                </div>
                <div class="grid gap-1.5">
                    <Label
                        :for="`skill-${index}-years`"
                        :class="index > 0 && 'sm:sr-only'"
                        >{{ trans('onboarding.skills.years') }}</Label
                    >
                    <Input
                        :id="`skill-${index}-years`"
                        v-model.number="skill.years"
                        type="number"
                        min="0"
                        max="50"
                        step="0.5"
                        inputmode="decimal"
                    />
                    <InputError :message="errors[`skills.${index}.years`]" />
                </div>
                <div class="grid gap-1.5">
                    <Label
                        :for="`skill-${index}-last-used`"
                        :class="index > 0 && 'sm:sr-only'"
                        >{{ trans('onboarding.skills.last_used') }}</Label
                    >
                    <NativeSelect
                        :id="`skill-${index}-last-used`"
                        v-model="skill.last_used_year"
                    >
                        <option :value="null">
                            {{ trans('onboarding.skills.last_used_unknown') }}
                        </option>
                        <option v-for="year in years" :key="year" :value="year">
                            {{
                                year === options.currentYear
                                    ? trans('onboarding.skills.last_used_now')
                                    : year
                            }}
                        </option>
                    </NativeSelect>
                    <InputError
                        :message="errors[`skills.${index}.last_used_year`]"
                    />
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="justify-self-end"
                    :class="index === 0 && 'sm:mt-6'"
                    :aria-label="
                        trans('onboarding.skills.remove', {
                            name: skill.name || String(index + 1),
                        })
                    "
                    @click="remove(index)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </Button>
            </li>
        </ul>

        <InputError :message="errors.skills" />

        <div class="flex flex-wrap items-center gap-3">
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="answers.skills.length >= options.maxSkills"
                @click="add"
            >
                <Plus class="size-4" aria-hidden="true" />
                {{ trans('onboarding.skills.add') }}
            </Button>
            <span class="text-xs text-muted-foreground">{{
                trans('onboarding.skills.limit', {
                    max: String(options.maxSkills),
                })
            }}</span>
        </div>
    </div>
</template>
