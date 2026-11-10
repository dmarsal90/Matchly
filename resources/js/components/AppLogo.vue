<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';

const name = usePage().props.name;
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-[#292D32] text-[#F47761]"
    >
        <AppLogoIcon class="size-6" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span
            class="mb-0.5 truncate text-[0.95rem] leading-tight font-semibold tracking-tight lowercase"
            >{{ name }}</span
        >
    </div>
</template>
