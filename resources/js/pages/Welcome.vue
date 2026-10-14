<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

const page = usePage();
const app = page.props.name;
const year = new Date().getFullYear();
</script>

<template>
    <Head :title="trans('app.tagline')" />

    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6"
        >
            <Link
                :href="page.props.auth.user ? dashboard() : '/'"
                class="flex items-center gap-2 font-semibold"
            >
                <AppLogoIcon class="size-7 fill-current" aria-hidden="true" />
                <span>{{ app }}</span>
            </Link>

            <nav class="flex items-center gap-1 sm:gap-2">
                <LanguageSwitcher />
                <ThemeToggle />
                <template v-if="page.props.auth.user">
                    <Button as-child variant="outline" size="sm">
                        <Link :href="dashboard()">
                            {{ trans('app.dashboard') }}
                        </Link>
                    </Button>
                </template>
                <template v-else>
                    <Button as-child variant="ghost" size="sm">
                        <Link :href="login()">{{ trans('app.log_in') }}</Link>
                    </Button>
                    <Button as-child size="sm" class="hidden sm:inline-flex">
                        <Link :href="register()">
                            {{ trans('app.register') }}
                        </Link>
                    </Button>
                </template>
            </nav>
        </header>

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
                {{ trans('app.hero_subtitle', { app }) }}
            </p>
            <Button as-child size="lg" class="mt-8">
                <Link :href="page.props.auth.user ? dashboard() : register()">
                    {{ trans('app.get_started') }}
                </Link>
            </Button>
        </main>

        <footer class="py-6 text-center text-sm text-muted-foreground">
            {{ trans('app.footer_rights', { year: String(year), app }) }}
        </footer>
    </div>
</template>
