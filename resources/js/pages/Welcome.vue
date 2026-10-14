<script setup lang="ts">
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Button } from '@/components/ui/button';
import { dashboard, register } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

const page = usePage();
</script>

<template>
    <Head :title="trans('app.tagline')" />

    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <PublicHeader />

        <main
            class="mx-auto flex w-full max-w-3xl flex-1 flex-col items-center justify-center px-4 py-16 text-center sm:px-6"
        >
            <p class="text-sm font-medium text-muted-foreground">
                {{ trans('app.tagline') }}
            </p>
            <h1
                class="mt-3 text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
            >
                {{ trans('app.hero_title') }}
            </h1>
            <p class="mt-5 text-lg text-pretty text-muted-foreground">
                {{ trans('app.hero_subtitle', { app: page.props.name }) }}
            </p>
            <Button as-child size="lg" class="mt-8">
                <Link :href="page.props.auth.user ? dashboard() : register()">
                    {{ trans('app.get_started') }}
                </Link>
            </Button>
        </main>

        <PublicFooter />
    </div>
</template>
