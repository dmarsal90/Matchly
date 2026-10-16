<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<!-- Two profiles that overlap: the lens in the middle is the match. -->
<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="10 14 44 36"
        fill="none"
        aria-hidden="true"
        :class="className"
        v-bind="$attrs"
    >
        <circle
            cx="26"
            cy="32"
            r="14"
            fill="currentColor"
            fill-opacity=".14"
            stroke="currentColor"
            stroke-width="3"
        />
        <circle
            cx="38"
            cy="32"
            r="14"
            fill="currentColor"
            fill-opacity=".14"
            stroke="currentColor"
            stroke-width="3"
        />
        <path
            d="M32 19.35A14 14 0 0 1 32 44.65A14 14 0 0 1 32 19.35Z"
            fill="var(--highlight)"
        />
    </svg>
</template>
