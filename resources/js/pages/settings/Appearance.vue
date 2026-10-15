<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'account.settings.appearance.head',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="trans('account.settings.appearance.head')" />

    <h1 class="sr-only">{{ trans('account.settings.appearance.head') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="trans('account.settings.appearance.head')"
            :description="trans('account.settings.appearance.description')"
        />
        <AppearanceTabs />
    </div>
</template>
