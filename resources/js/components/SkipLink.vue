<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
</script>

<template>
    <!-- WCAG 2.4.1: keyboard users jump past the navigation straight to the page content. -->
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-primary-foreground focus:shadow-lg focus:ring-2 focus:ring-ring focus:outline-none"
    >
        {{ trans('common.skip_to_content') }}
    </a>
</template>
