<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ExternalLink } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';

defineProps<{ href: string; size?: 'sm' | 'default' | 'lg' }>();
</script>

<template>
    <!-- Always the official posting, never a form of ours: we do not apply on anyone's behalf. -->
    <Button
        as="a"
        :href="href"
        target="_blank"
        rel="noopener noreferrer nofollow"
        :size="size ?? 'sm'"
        :title="trans('jobs.card.apply_hint')"
    >
        {{ trans('jobs.card.apply') }}
        <ExternalLink class="size-3.5" aria-hidden="true" />
        <span class="sr-only">({{ trans('jobs.card.apply_hint') }})</span>
    </Button>
</template>
