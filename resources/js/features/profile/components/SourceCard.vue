<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/features/profile/lib/format';
import type { SkillSource, SourceState } from '@/features/profile/types';
import {
    destroy as discardImport,
    show as review,
} from '@/routes/skills/imports';
import { destroy as deleteSource } from '@/routes/skills/sources';
import { Link, router } from '@inertiajs/vue3';
import { CircleAlert, Trash2 } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import type { Component } from 'vue';

const props = defineProps<{
    source: SkillSource;
    state: SourceState;
    icon: Component;
}>();

function remove(): void {
    if (window.confirm(trans('skills.sources.delete_confirm'))) {
        router.delete(deleteSource.url(props.source), { preserveScroll: true });
    }
}

function discard(id: number): void {
    router.delete(discardImport.url(id), { preserveScroll: true });
}
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border p-4"
        :aria-labelledby="`source-${source}`"
    >
        <header class="flex items-start gap-3">
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted"
                aria-hidden="true"
            >
                <component :is="icon" class="size-5" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 :id="`source-${source}`" class="font-medium">
                        {{ trans(`skills.sources.${source}.title`) }}
                    </h3>
                    <Badge v-if="state.skills_count > 0" variant="secondary">{{
                        transChoice(
                            'skills.sources.skills_count',
                            state.skills_count,
                        )
                    }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ trans(`skills.sources.${source}.description`) }}
                </p>
                <p
                    v-if="state.confirmed?.confirmed_at"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{
                        trans('skills.sources.confirmed_at', {
                            date: formatDate(state.confirmed.confirmed_at),
                        })
                    }}
                </p>
            </div>
        </header>

        <div
            v-if="state.pending"
            class="rounded-lg border bg-muted/40 p-3 text-sm"
            role="status"
            aria-live="polite"
        >
            <p
                v-if="state.pending.status === 'processing'"
                class="flex items-center gap-2"
            >
                <Spinner class="size-4" />
                {{ trans('skills.sources.processing') }}
            </p>
            <div
                v-else-if="state.pending.status === 'pending_review'"
                class="flex flex-wrap items-center justify-between gap-2"
            >
                <span>{{ trans('skills.sources.pending') }}</span>
                <div class="flex gap-2">
                    <Button size="sm" as-child>
                        <Link :href="review(state.pending.id)">{{
                            trans('skills.sources.review')
                        }}</Link>
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        @click="discard(state.pending.id)"
                        >{{ trans('skills.sources.discard') }}</Button
                    >
                </div>
            </div>
            <div
                v-else-if="state.pending.status === 'failed'"
                class="flex flex-wrap items-start justify-between gap-2 text-destructive"
            >
                <p class="flex items-start gap-2">
                    <CircleAlert
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>{{
                        state.pending.error
                            ? trans(`skills.errors.${state.pending.error}`)
                            : trans('skills.sources.failed')
                    }}</span>
                </p>
                <Button
                    size="sm"
                    variant="ghost"
                    @click="discard(state.pending.id)"
                    >{{ trans('skills.sources.discard') }}</Button
                >
            </div>
        </div>

        <slot />

        <footer
            v-if="state.skills_count > 0 || state.confirmed"
            class="mt-auto flex justify-end border-t pt-3"
        >
            <Button
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive"
                @click="remove"
            >
                <Trash2 class="size-4" aria-hidden="true" />
                {{ trans('skills.sources.delete') }}
            </Button>
        </footer>
    </section>
</template>
