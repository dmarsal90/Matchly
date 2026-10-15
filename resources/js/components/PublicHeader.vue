<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

const page = usePage();
</script>

<template>
    <header
        class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6"
    >
        <Link :href="home()" class="flex items-center gap-2 font-semibold">
            <AppLogoIcon class="size-7 fill-current" aria-hidden="true" />
            <span>{{ page.props.name }}</span>
        </Link>

        <nav class="flex items-center gap-1 sm:gap-2">
            <LanguageSwitcher />
            <ThemeToggle />
            <Button
                v-if="page.props.auth.user"
                as-child
                variant="outline"
                size="sm"
            >
                <Link :href="dashboard()">{{ trans('app.dashboard') }}</Link>
            </Button>
            <template v-else>
                <Button as-child variant="ghost" size="sm">
                    <Link :href="login()">{{ trans('app.log_in') }}</Link>
                </Button>
                <Button as-child size="sm" class="hidden sm:inline-flex">
                    <Link :href="register()">{{ trans('app.register') }}</Link>
                </Button>
            </template>
        </nav>
    </header>
</template>
