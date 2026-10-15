<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';
import type { Resume } from '@/features/applications/types';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ contact: Resume['contact'] }>();

const rows = computed(() =>
    [
        { label: trans('quick_apply.contact.name'), value: props.contact.name },
        {
            label: trans('quick_apply.contact.email'),
            value: props.contact.email,
        },
        {
            label: trans('quick_apply.contact.phone'),
            value: props.contact.phone,
        },
        {
            label: trans('quick_apply.contact.location'),
            value: props.contact.location,
        },
        ...props.contact.links.map((link) => ({
            label: trans(`quick_apply.links.${link.label}`),
            value: link.url,
        })),
    ].filter((row): row is { label: string; value: string } => !!row.value),
);
</script>

<template>
    <dl class="divide-y rounded-lg border">
        <div
            v-for="row in rows"
            :key="row.label"
            class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
        >
            <dt class="text-muted-foreground">{{ row.label }}</dt>
            <dd class="flex min-w-0 items-center gap-1">
                <span class="truncate font-medium">{{ row.value }}</span>
                <CopyButton :text="row.value" :label="row.label" />
            </dd>
        </div>
    </dl>
</template>
