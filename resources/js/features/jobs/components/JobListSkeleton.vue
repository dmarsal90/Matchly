<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

defineProps<{ count?: number }>();
</script>

<template>
    <div class="space-y-3" aria-hidden="true">
        <div
            v-for="index in count ?? 5"
            :key="index"
            class="flex gap-4 rounded-xl border p-4"
        >
            <Skeleton class="size-11 rounded-lg" />
            <div class="flex-1 space-y-2">
                <Skeleton class="h-4 w-2/3" />
                <Skeleton class="h-3 w-1/3" />
                <Skeleton class="h-3 w-1/2" />
            </div>
        </div>
    </div>
</template>
