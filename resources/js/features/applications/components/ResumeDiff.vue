<script setup lang="ts">
import type { DiffPart } from '@/features/applications/lib/diff';
import { trans } from 'laravel-vue-i18n';

defineProps<{ parts: DiffPart<string>[]; inline?: boolean }>();
</script>

<template>
    <p v-if="inline" class="text-sm leading-relaxed">
        <template v-for="(part, index) in parts" :key="index">
            <ins
                v-if="part.type === 'added'"
                class="rounded bg-emerald-500/15 text-emerald-800 no-underline dark:text-emerald-300"
                :title="trans('quick_apply.editor.added')"
                >{{ part.value }}</ins
            >
            <del
                v-else-if="part.type === 'removed'"
                class="rounded bg-rose-500/15 text-rose-800 dark:text-rose-300"
                :title="trans('quick_apply.editor.removed')"
                >{{ part.value }}</del
            >
            <span v-else>{{ part.value }}</span>
        </template>
    </p>
    <ul v-else class="space-y-0.5 text-sm">
        <li
            v-for="(part, index) in parts"
            :key="index"
            class="rounded px-2 py-0.5"
            :class="{
                'bg-emerald-500/10 text-emerald-800 dark:text-emerald-300':
                    part.type === 'added',
                'bg-rose-500/10 text-rose-800 line-through dark:text-rose-300':
                    part.type === 'removed',
            }"
        >
            <span v-if="part.type !== 'same'" class="sr-only"
                >{{
                    part.type === 'added'
                        ? trans('quick_apply.editor.added')
                        : trans('quick_apply.editor.removed')
                }}:
            </span>
            {{ part.value }}
        </li>
    </ul>
</template>
