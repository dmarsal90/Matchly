<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, useId } from 'vue';

defineProps<{ label: string; count: number }>();

const open = ref(true);
const id = useId();
</script>

<template>
    <section class="space-y-3">
        <h2>
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 rounded-lg px-1 py-2 text-left hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :aria-expanded="open"
                :aria-controls="id"
                :title="
                    trans(open ? 'jobs.group.collapse' : 'jobs.group.expand')
                "
                @click="open = !open"
            >
                <span class="font-semibold">{{ label }}</span>
                <span
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    {{ trans('jobs.group.count', { count: String(count) }) }}
                    <ChevronDown
                        class="size-4 transition-transform"
                        :class="!open && '-rotate-90'"
                        aria-hidden="true"
                    />
                </span>
            </button>
        </h2>
        <div v-show="open" :id="id" class="space-y-3">
            <slot />
        </div>
    </section>
</template>
