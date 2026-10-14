import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import i18n from 'laravel-vue-i18n/vite';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const port = Number(env.VITE_PORT ?? 5173);
    // Inside Docker the dev server binds to 0.0.0.0 but the browser reaches it via localhost.
    const inDocker = env.VITE_DOCKER === 'true';

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.ts'],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            inertia(),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            wayfinder({
                formVariants: true,
            }),
            i18n(),
        ],
        server: {
            host: inDocker ? '0.0.0.0' : undefined,
            port,
            strictPort: true,
            hmr: inDocker ? { host: env.VITE_HMR_HOST ?? 'localhost', port } : undefined,
            watch: {
                ignored: [
                    '**/.claude/**',
                    '**/.idea/**',
                    '**/.remember/**',
                    '**/storage/**',
                    '**/vendor/**',
                ],
            },
        },
    };
});
