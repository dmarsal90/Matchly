<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import DateFilter from '@/features/jobs/components/DateFilter.vue';
import type {
    JobFilters,
    JobOptions,
    MultiFilterKey,
} from '@/features/jobs/types';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

const props = defineProps<{
    filters: JobFilters;
    options: JobOptions;
    activeCount: number;
    /** Signed-in viewers can filter by their compatibility score. */
    canMatch?: boolean;
}>();

const MIN_SCORES = [50, 60, 70, 80, 90];
const emit = defineEmits<{
    toggle: [key: MultiFilterKey, value: string];
    set: [key: keyof JobFilters, value: string | number | null];
    clear: [];
}>();

const sections: {
    key: Exclude<MultiFilterKey, 'source' | 'tag'>;
    collapsed: boolean;
}[] = [
    { key: 'work_mode', collapsed: false },
    { key: 'scope', collapsed: false },
    { key: 'type', collapsed: false },
    { key: 'seniority', collapsed: true },
    { key: 'category', collapsed: true },
    { key: 'role', collapsed: true },
];

const salary = ref<number | null>(props.filters.salary_min);
watch(
    () => props.filters.salary_min,
    (value) => (salary.value = value),
);

function applySalary(): void {
    emit(
        'set',
        'salary_min',
        salary.value && salary.value > 0 ? Math.round(salary.value) : null,
    );
}
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">{{ trans('jobs.filters.title') }}</h2>
            <Button
                v-if="activeCount > 0"
                variant="link"
                size="sm"
                class="h-auto p-0"
                @click="emit('clear')"
            >
                {{ trans('jobs.filters.clear') }}
            </Button>
        </div>

        <div v-if="canMatch" class="grid gap-1.5">
            <label for="min-score" class="text-sm font-medium">{{
                trans('jobs.filters.min_score')
            }}</label>
            <NativeSelect
                id="min-score"
                :model-value="filters.min_score"
                @update:model-value="
                    emit('set', 'min_score', $event ? Number($event) : null)
                "
            >
                <option :value="null">
                    {{ trans('jobs.filters.any_score') }}
                </option>
                <option v-for="score in MIN_SCORES" :key="score" :value="score">
                    ≥ {{ score }}
                </option>
            </NativeSelect>
        </div>

        <DateFilter
            :presets="options.posted"
            :posted="filters.posted"
            :from="filters.from"
            :to="filters.to"
            @posted="emit('set', 'posted', $event)"
            @from="emit('set', 'from', $event)"
            @to="emit('set', 'to', $event)"
        />

        <Separator />

        <details
            v-for="section in sections"
            :key="section.key"
            :open="!section.collapsed || filters[section.key].length > 0"
            class="group"
        >
            <summary
                class="flex cursor-pointer list-none items-center justify-between text-sm font-medium"
            >
                {{ trans(`jobs.filters.${section.key}`) }}
                <span
                    v-if="filters[section.key].length"
                    class="rounded-full bg-primary px-1.5 text-xs text-primary-foreground"
                    >{{ filters[section.key].length }}</span
                >
            </summary>
            <div class="mt-2 grid gap-1.5">
                <label
                    v-for="value in options[section.key]"
                    :key="value"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        class="size-4 accent-primary"
                        :checked="filters[section.key].includes(value)"
                        @change="emit('toggle', section.key, value)"
                    />
                    {{ trans(`jobs.${section.key}.${value}`) }}
                </label>
            </div>
        </details>

        <Separator />

        <fieldset class="grid gap-2">
            <legend class="mb-1 text-sm font-medium">
                {{ trans('jobs.filters.salary') }}
            </legend>
            <div class="grid grid-cols-[1fr_auto] gap-2">
                <Input
                    v-model.number="salary"
                    type="number"
                    min="0"
                    step="5000"
                    inputmode="numeric"
                    :aria-label="trans('jobs.filters.salary')"
                    @change="applySalary"
                    @keydown.enter.prevent="applySalary"
                />
                <NativeSelect
                    :model-value="filters.currency"
                    :aria-label="trans('jobs.filters.currency')"
                    class="w-24"
                    @update:model-value="
                        emit('set', 'currency', $event ?? null)
                    "
                >
                    <option :value="null">
                        {{ trans('jobs.filters.any_currency') }}
                    </option>
                    <option
                        v-for="currency in options.currencies"
                        :key="currency"
                        :value="currency"
                    >
                        {{ currency }}
                    </option>
                </NativeSelect>
            </div>
        </fieldset>

        <Separator />

        <fieldset v-if="options.tag.length" class="grid gap-2">
            <legend class="mb-1 text-sm font-medium">
                {{ trans('jobs.filters.tag') }}
            </legend>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="tag in options.tag"
                    :key="tag"
                    type="button"
                    :aria-pressed="filters.tag.includes(tag)"
                    class="rounded-md border px-2 py-0.5 text-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        filters.tag.includes(tag)
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent'
                    "
                    @click="emit('toggle', 'tag', tag)"
                >
                    {{ tag }}
                </button>
            </div>
        </fieldset>

        <details :open="filters.source.length > 0">
            <summary class="cursor-pointer text-sm font-medium">
                {{ trans('jobs.filters.source') }}
            </summary>
            <div class="mt-2 grid gap-1.5">
                <label
                    v-for="source in options.source"
                    :key="source.key"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        class="size-4 accent-primary"
                        :checked="filters.source.includes(source.key)"
                        @change="emit('toggle', 'source', source.key)"
                    />
                    {{ source.name }}
                </label>
            </div>
        </details>
    </div>
</template>
