<script setup lang="ts">
import { CircleCheck, CircleAlert } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { Badge } from '@/components/ui/badge';
import type { AtsReport } from '@/features/applications/types';

defineProps<{ ats: AtsReport }>();

const fix = (check: AtsReport['checks'][number]): string =>
    trans(`quick_apply.ats.fix.${check.key}`, check.params);
</script>

<template>
    <section
        class="space-y-3 rounded-xl border p-4 text-sm"
        aria-labelledby="ats-title"
    >
        <div class="flex items-baseline justify-between gap-2">
            <h2 id="ats-title" class="font-medium">
                {{ trans('quick_apply.ats.title') }}
            </h2>
            <p class="text-lg font-semibold tabular-nums">
                {{
                    trans('quick_apply.ats.score', { score: String(ats.score) })
                }}
            </p>
        </div>
        <ul class="space-y-1.5">
            <li
                v-for="check in ats.checks"
                :key="check.key"
                class="flex items-start gap-2"
            >
                <CircleCheck
                    v-if="check.points >= check.max"
                    class="mt-0.5 size-4 shrink-0 text-emerald-600"
                    aria-hidden="true"
                />
                <CircleAlert
                    v-else
                    class="mt-0.5 size-4 shrink-0 text-amber-600"
                    aria-hidden="true"
                />
                <span>
                    {{ trans(`quick_apply.ats.checks.${check.key}`) }}
                    <span class="text-muted-foreground tabular-nums">
                        ({{ check.points }}/{{ check.max }})
                    </span>
                    <span
                        v-if="check.points < check.max"
                        class="block text-muted-foreground"
                    >
                        {{ fix(check) }}
                    </span>
                </span>
            </li>
        </ul>
        <div
            v-if="ats.keywords.matched.length + ats.keywords.missing.length"
            class="grid gap-1.5"
        >
            <p class="text-xs text-muted-foreground">
                {{
                    trans('quick_apply.ats.kw', {
                        matched: String(ats.keywords.matched.length),
                        total: String(
                            ats.keywords.matched.length +
                                ats.keywords.missing.length,
                        ),
                    })
                }}
            </p>
            <ul class="flex flex-wrap gap-1.5">
                <li v-for="keyword in ats.keywords.matched" :key="keyword">
                    <Badge variant="secondary">{{ keyword }}</Badge>
                </li>
                <li
                    v-for="(keyword, index) in ats.keywords.missing"
                    :key="keyword"
                >
                    <Badge variant="outline" class="text-muted-foreground">
                        <span v-if="index === 0" class="sr-only">
                            {{ trans('quick_apply.ats.kwm') }}:
                        </span>
                        {{ keyword }}
                    </Badge>
                </li>
            </ul>
        </div>
    </section>
</template>
