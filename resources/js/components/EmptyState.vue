<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{ title: string; description?: string; icon?: Component }>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-primary/25 bg-gradient-to-b from-accent/40 to-transparent px-6 py-14 text-center"
    >
        <span
            v-if="icon"
            class="mb-4 flex size-12 items-center justify-center rounded-full bg-accent text-accent-foreground ring-8 ring-accent/40"
        >
            <component :is="icon" class="size-6" aria-hidden="true" />
        </span>
        <p class="text-base font-semibold text-balance">{{ title }}</p>
        <p
            v-if="description"
            class="mt-1 max-w-sm text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>
