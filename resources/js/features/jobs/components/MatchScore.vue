<script setup lang="ts">
import type { JobMatchSummary } from '@/features/jobs/types';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{ match: JobMatchSummary; size?: 'sm' | 'lg' }>(),
    { size: 'sm' },
);

const RADIUS = 16;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const dash = computed(
    () => `${(props.match.score / 100) * CIRCUMFERENCE} ${CIRCUMFERENCE}`,
);
const tone = computed(() =>
    !props.match.eligible
        ? 'text-muted-foreground'
        : {
              high: 'text-emerald-700 dark:text-emerald-400',
              medium: 'text-amber-700 dark:text-amber-400',
              low: 'text-rose-700 dark:text-rose-400',
          }[props.match.level],
);
const label = computed(() =>
    props.match.eligible
        ? `${trans('matching.score_label', { score: String(props.match.score) })} · ${trans(`matching.levels.${props.match.level}`)}`
        : `${trans('matching.ineligible_label')} · ${trans('matching.score_label', { score: String(props.match.score) })}`,
);
</script>

<template>
    <div
        class="inline-flex shrink-0 flex-col items-center gap-0.5"
        role="img"
        :aria-label="label"
        :title="label"
    >
        <div
            class="relative"
            :class="[tone, size === 'lg' ? 'size-20' : 'size-11']"
        >
            <svg viewBox="0 0 36 36" class="size-full -rotate-90">
                <circle
                    cx="18"
                    cy="18"
                    :r="RADIUS"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    class="opacity-15"
                />
                <circle
                    cx="18"
                    cy="18"
                    :r="RADIUS"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    :stroke-dasharray="dash"
                    :stroke-opacity="match.eligible ? 1 : 0.4"
                />
            </svg>
            <span
                class="absolute inset-0 flex items-center justify-center font-semibold text-foreground tabular-nums"
                :class="size === 'lg' ? 'text-xl' : 'text-xs'"
                aria-hidden="true"
                >{{ match.score }}</span
            >
        </div>
        <span
            v-if="size === 'lg' || !match.eligible"
            class="text-[11px] leading-none font-medium"
            :class="tone"
            aria-hidden="true"
            >{{
                match.eligible
                    ? trans(`matching.levels.${match.level}`)
                    : trans('matching.ineligible_label')
            }}</span
        >
    </div>
</template>
