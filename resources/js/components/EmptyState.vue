<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{ title: string; description?: string; icon?: Component }>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-14 text-center"
    >
        <component
            :is="icon"
            v-if="icon"
            class="mb-3 size-8 text-muted-foreground"
            aria-hidden="true"
        />
        <p class="font-medium">{{ title }}</p>
        <p
            v-if="description"
            class="mt-1 max-w-sm text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>
