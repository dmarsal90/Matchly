<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    presets: string[];
    posted: string | null;
    from: string | null;
    to: string | null;
}>();
const emit = defineEmits<{
    posted: [value: string | null];
    from: [value: string | null];
    to: [value: string | null];
}>();

const today = new Date().toISOString().slice(0, 10);
const custom = computed(() => props.from !== null || props.to !== null);
</script>

<template>
    <fieldset class="grid gap-2">
        <legend class="mb-1 text-sm font-medium">
            {{ trans('jobs.filters.posted') }}
        </legend>
        <label class="flex items-center gap-2 text-sm">
            <input
                type="radio"
                name="posted"
                class="accent-primary"
                :checked="!posted && !custom"
                @change="emit('posted', null)"
            />
            {{ trans('jobs.filters.any_date') }}
        </label>
        <label
            v-for="preset in presets"
            :key="preset"
            class="flex items-center gap-2 text-sm"
        >
            <input
                type="radio"
                name="posted"
                class="accent-primary"
                :checked="posted === preset"
                @change="emit('posted', preset)"
            />
            {{ trans(`jobs.posted.${preset}`) }}
        </label>

        <details class="mt-1" :open="custom">
            <summary class="cursor-pointer text-sm text-muted-foreground">
                {{ trans('jobs.filters.custom_range') }}
            </summary>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <div class="grid gap-1">
                    <Label for="posted-from" class="text-xs">{{
                        trans('jobs.filters.from')
                    }}</Label>
                    <Input
                        id="posted-from"
                        type="date"
                        :max="to ?? today"
                        :model-value="from ?? ''"
                        @change="
                            emit(
                                'from',
                                ($event.target as HTMLInputElement).value ||
                                    null,
                            )
                        "
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="posted-to" class="text-xs">{{
                        trans('jobs.filters.to')
                    }}</Label>
                    <Input
                        id="posted-to"
                        type="date"
                        :min="from ?? undefined"
                        :max="today"
                        :model-value="to ?? ''"
                        @change="
                            emit(
                                'to',
                                ($event.target as HTMLInputElement).value ||
                                    null,
                            )
                        "
                    />
                </div>
            </div>
        </details>
    </fieldset>
</template>
