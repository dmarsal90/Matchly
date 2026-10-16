<script setup lang="ts">
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

export type SalaryBenchmark = {
    currency: string;
    low: number;
    median: number;
    high: number;
    sample: number;
};

const props = defineProps<{ benchmark: SalaryBenchmark }>();

const money = (value: number): string => {
    try {
        return new Intl.NumberFormat(getActiveLanguage(), {
            style: 'currency',
            currency: props.benchmark.currency,
            maximumFractionDigits: 0,
        }).format(value);
    } catch {
        return `${value} ${props.benchmark.currency}`;
    }
};

const range = computed(() => ({
    low: money(props.benchmark.low),
    high: money(props.benchmark.high),
}));
</script>

<template>
    <section class="space-y-1 rounded-xl border p-4 text-sm">
        <h2 class="font-semibold">{{ trans('jobs.benchmark.title') }}</h2>
        <p>
            {{
                trans('jobs.benchmark.summary', {
                    median: money(benchmark.median),
                    low: range.low,
                    high: range.high,
                })
            }}
        </p>
        <p class="text-xs text-muted-foreground">
            {{
                trans('jobs.benchmark.note', {
                    count: String(benchmark.sample),
                })
            }}
        </p>
    </section>
</template>
