<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

/** Native <select> styled like Input: accessible and mobile-friendly by default. */
const props = defineProps<{ class?: HTMLAttributes['class'] }>();
const model = defineModel<string | number | null>();
</script>

<template>
    <select
        v-model="model"
        data-slot="native-select"
        :class="
            cn(
                'h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30',
                // Mobile/dark popups paint options on the page colour; without this they render blank.
                '[&>option]:bg-background [&>option]:text-foreground',
                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                props.class,
            )
        "
    >
        <slot />
    </select>
</template>
