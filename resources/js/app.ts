import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { pageTitle, setAppName } from '@/lib/brand';
import { initializeFlashToast } from '@/lib/flashToast';
import { resolveLanguage } from '@/lib/i18n';
import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { i18nVue } from 'laravel-vue-i18n';
import { createPinia } from 'pinia';

void createInertiaApp({
    title: pageTitle,
    layout: (name, page) => {
        switch (true) {
            case name === 'Welcome':
            case name.startsWith('legal/'):
                return null;
            // Public listing: the app shell when signed in, a light public shell otherwise.
            case name.startsWith('jobs/'):
            case name === 'alerts/Unsubscribed':
                return (page.props as { auth?: { user: unknown } }).auth?.user
                    ? AppLayout
                    : PublicLayout;
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

        // The websocket address comes from the server, not from build-time
        // variables, so the same bundle works in every environment.
        const reverb = page.props.reverb;
        if (reverb) {
            configureEcho({
                broadcaster: 'reverb',
                key: reverb.key,
                wsHost: reverb.host,
                wsPort: reverb.port,
                wssPort: reverb.port,
                forceTLS: reverb.scheme === 'https',
                enabledTransports: ['ws', 'wss'],
            });
        }

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
