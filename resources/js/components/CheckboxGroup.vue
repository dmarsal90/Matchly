<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

defineProps<{
    name: string;
    legend: string;
    options: { value: string; label: string }[];
}>();

const model = defineModel<string[]>({ required: true });

function toggle(value: string, checked: boolean | 'indeterminate'): void {
    model.value =
        checked === true
            ? [...new Set([...model.value, value])]
            : model.value.filter((item) => item !== value);
}
</script>

<template>
    <fieldset class="grid gap-2">
        <legend class="mb-1 text-sm font-medium">{{ legend }}</legend>
        <div class="flex flex-wrap gap-x-5 gap-y-2">
            <div
                v-for="option in options"
                :key="option.value"
                class="flex items-center gap-2"
            >
                <Checkbox
                    :id="`${name}-${option.value}`"
                    :model-value="model.includes(option.value)"
                    @update:model-value="toggle(option.value, $event)"
                />
                <Label :for="`${name}-${option.value}`" class="font-normal">{{
                    option.label
                }}</Label>
            </div>
        </div>
    </fieldset>
</template>
