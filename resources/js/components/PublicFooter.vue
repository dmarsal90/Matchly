<script setup lang="ts">
import { privacy, terms } from '@/routes/legal';
import { Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

const page = usePage();
const year = new Date().getFullYear();
</script>

<template>
    <footer
        class="flex flex-col items-center gap-2 py-6 text-sm text-muted-foreground sm:flex-row sm:justify-center sm:gap-6"
    >
        <span>{{
            trans('app.footer_rights', {
                year: String(year),
                app: page.props.name,
            })
        }}</span>
        <Link :href="privacy()" class="hover:text-foreground">{{
            trans('legal.privacy.title')
        }}</Link>
        <Link :href="terms()" class="hover:text-foreground">{{
            trans('legal.terms.title')
        }}</Link>
    </footer>
</template>
