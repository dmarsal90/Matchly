<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ currentPage: number; lastPage: number }>();
const emit = defineEmits<{ page: [value: number] }>();

/** 1 … 4 5 [6] 7 8 … 20 */
const pages = computed<(number | '…')[]>(() => {
    const { currentPage: current, lastPage: last } = props;
    const window = new Set(
        [
            1,
            last,
            current - 2,
            current - 1,
            current,
            current + 1,
            current + 2,
        ].filter((page) => page >= 1 && page <= last),
    );
    const sorted = [...window].sort((a, b) => a - b);

    return sorted.flatMap((page, index) =>
        index > 0 && page - sorted[index - 1] > 1
            ? ['…' as const, page]
            : [page],
    );
});
</script>

<template>
    <nav
        v-if="lastPage > 1"
        :aria-label="trans('jobs.pagination.label')"
        class="flex items-center justify-center gap-1"
    >
        <Button
            variant="ghost"
            size="icon"
            :disabled="currentPage <= 1"
            :aria-label="trans('jobs.pagination.previous')"
            @click="emit('page', currentPage - 1)"
        >
            <ChevronLeft class="size-4" aria-hidden="true" />
        </Button>
        <template v-for="(page, index) in pages" :key="`${page}-${index}`">
            <span
                v-if="page === '…'"
                class="px-2 text-muted-foreground"
                aria-hidden="true"
                >…</span
            >
            <Button
                v-else
                :variant="page === currentPage ? 'default' : 'ghost'"
                size="icon"
                :aria-current="page === currentPage ? 'page' : undefined"
                :aria-label="
                    trans('jobs.pagination.page', { page: String(page) })
                "
                @click="page !== currentPage && emit('page', page)"
            >
                {{ page }}
            </Button>
        </template>
        <Button
            variant="ghost"
            size="icon"
            :disabled="currentPage >= lastPage"
            :aria-label="trans('jobs.pagination.next')"
            @click="emit('page', currentPage + 1)"
        >
            <ChevronRight class="size-4" aria-hidden="true" />
        </Button>
    </nav>
</template>
