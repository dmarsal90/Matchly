<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { index as jobsIndex } from '@/routes/jobs';
import { index } from '@/routes/market';
import { Head, Link } from '@inertiajs/vue3';
import { ChartNoAxesColumn } from '@lucide/vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';

type Row = {
    skill: string;
    name: string;
    own: boolean;
    jobs: number;
    share: number;
    trend: 'up' | 'down' | 'flat' | null;
    salary: {
        currency: string;
        low: number;
        median: number;
        high: number;
        sample: number;
    } | null;
};

defineProps<{
    total: number;
    skills: Row[];
    minSalarySample: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'nav.market', href: index() }] },
});

function money(value: number, currency: string): string {
    try {
        return new Intl.NumberFormat(getActiveLanguage(), {
            style: 'currency',
            currency,
            maximumFractionDigits: 0,
        }).format(value);
    } catch {
        return `${value} ${currency}`;
    }
}
</script>

<template>
    <Head :title="trans('market.title')" />

    <div class="mx-auto w-full max-w-4xl space-y-6 px-4 py-6">
        <Heading
            :title="trans('market.title')"
            :description="trans('market.description', { total: String(total) })"
        />

        <EmptyState
            v-if="skills.length === 0"
            :icon="ChartNoAxesColumn"
            :title="trans('market.empty')"
        />

        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    {{
                        trans('market.table')
                    }}
                </caption>
                <thead>
                    <tr class="border-b text-left text-muted-foreground">
                        <th scope="col" class="py-2 pr-3 font-medium">
                            {{ trans('market.skill') }}
                        </th>
                        <th
                            scope="col"
                            class="py-2 pr-3 text-right font-medium"
                        >
                            {{ trans('market.jobs') }}
                        </th>
                        <th scope="col" class="py-2 pr-3 font-medium">
                            {{ trans('market.trend') }}
                        </th>
                        <th scope="col" class="py-2 font-medium">
                            {{ trans('market.pay') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in skills" :key="row.skill" class="border-b">
                        <th scope="row" class="py-2 pr-3 text-left font-medium">
                            <Link
                                :href="
                                    jobsIndex({ query: { tag: [row.skill] } })
                                "
                                class="hover:underline"
                                >{{ row.name }}</Link
                            >
                            <Badge
                                :variant="row.own ? 'secondary' : 'outline'"
                                class="ml-2"
                                >{{
                                    row.own
                                        ? trans('market.yours')
                                        : trans('market.missing')
                                }}</Badge
                            >
                        </th>
                        <td class="py-2 pr-3 text-right tabular-nums">
                            {{ row.jobs }}
                            <span class="text-xs text-muted-foreground"
                                >({{ row.share }} %)</span
                            >
                        </td>
                        <td class="py-2 pr-3">
                            {{
                                row.trend
                                    ? trans(`market.trends.${row.trend}`)
                                    : trans('market.trends.unknown')
                            }}
                        </td>
                        <td class="py-2">
                            <template v-if="row.salary">
                                {{
                                    money(
                                        row.salary.median,
                                        row.salary.currency,
                                    )
                                }}
                                <span class="text-xs text-muted-foreground"
                                    >({{
                                        money(
                                            row.salary.low,
                                            row.salary.currency,
                                        )
                                    }}
                                    –
                                    {{
                                        money(
                                            row.salary.high,
                                            row.salary.currency,
                                        )
                                    }}
                                    ·
                                    {{
                                        trans('market.sample', {
                                            count: String(row.salary.sample),
                                        })
                                    }})</span
                                >
                            </template>
                            <span v-else class="text-muted-foreground">{{
                                trans('market.no_pay', {
                                    min: String(minSalarySample),
                                })
                            }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="mt-3 text-xs text-muted-foreground">
                {{ trans('market.note') }}
            </p>
        </div>
    </div>
</template>
