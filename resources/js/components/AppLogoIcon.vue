<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });
type Props = { className?: HTMLAttributes['class'] };
defineProps<Props>();
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" aria-hidden="true" :class="className" v-bind="$attrs">
        <path d="m24 5 18 9.5L24 24 6 14.5 24 5Z" fill="currentColor" />
        <path d="m6 23.5 18 9.5 18-9.5" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
        <path d="m6 32.5 18 9.5 18-9.5" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
