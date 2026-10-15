<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { index } from '@/routes/alerts';
import { Head, Link } from '@inertiajs/vue3';
import { MailX } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';

defineProps<{ name: string }>();
</script>

<template>
    <Head :title="trans('alerts.unsubscribed.title')" />

    <div
        class="mx-auto flex max-w-md flex-col items-center gap-4 px-4 py-16 text-center"
    >
        <MailX class="size-10 text-muted-foreground" aria-hidden="true" />
        <h1 class="text-xl font-semibold">
            {{ trans('alerts.unsubscribed.title') }}
        </h1>
        <p class="text-sm text-muted-foreground">
            {{ trans('alerts.unsubscribed.description', { name }) }}
        </p>
        <Button as-child>
            <Link :href="index()">{{
                trans('alerts.unsubscribed.manage')
            }}</Link>
        </Button>
    </div>
</template>
