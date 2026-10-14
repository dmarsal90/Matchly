import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { pageTitle, setAppName } from '@/lib/brand';
import { initializeFlashToast } from '@/lib/flashToast';
import { resolveLanguage } from '@/lib/i18n';
import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { i18nVue } from 'laravel-vue-i18n';
import { createPinia } from 'pinia';

configureEcho({
    broadcaster: 'reverb',
});

void createInertiaApp({
    title: pageTitle,
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name.startsWith('legal/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app, { page }) => {
        setAppName(page.props.name);

        app.use(createPinia());
        app.use(i18nVue, {
            lang: page.props.locale,
            fallbackLang: 'en',
            resolve: resolveLanguage,
        });

        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
