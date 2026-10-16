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
        viewBox="8 16 48 32"
        fill="none"
        aria-hidden="true"
        :class="className"
        v-bind="$attrs"
    >
        <circle cx="22.9" cy="32" r="13" fill="currentColor" />
        <circle cx="41.1" cy="32" r="13" fill="currentColor" />
        <path
            d="M32 22.72A13 13 0 0 1 32 41.28A13 13 0 0 1 32 22.72Z"
            fill="var(--highlight)"
        />
    </svg>
</template>
