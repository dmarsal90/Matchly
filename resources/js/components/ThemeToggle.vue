<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/types';
import { Monitor, Moon, Sun } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const { appearance, updateAppearance } = useAppearance();

const order: Appearance[] = ['light', 'dark', 'system'];
const icons = { light: Sun, dark: Moon, system: Monitor } as const;

const next = computed<Appearance>(
    () => order[(order.indexOf(appearance.value) + 1) % order.length],
);
const label = computed(
    () => `${trans('app.theme')}: ${trans(`app.theme_${appearance.value}`)}`,
);
</script>

<template>
    <button
        type="button"
        class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        :aria-label="label"
        :title="label"
        @click="updateAppearance(next)"
    >
        <component :is="icons[appearance]" class="size-4" aria-hidden="true" />
    </button>
</template>
