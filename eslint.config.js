import js from '@eslint/js';
import {
    defineConfigWithVueTs,
    vueTsConfigs,
} from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

export default defineConfigWithVueTs(
    {
        ignores: [
            'vendor',
            'node_modules',
            'public',
            'bootstrap/ssr',
            'storage',
            '.remember',
            '.idea',
            'coverage',
            'playwright-report',
            'test-results',
            'resources/js/components/ui/*',
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
    },
    js.configs.recommended,
    vue.configs['flat/recommended'],
    vueTsConfigs.recommended,
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/require-default-prop': 'off',
            '@typescript-eslint/no-explicit-any': 'error',
            '@typescript-eslint/consistent-type-imports': [
                'error',
                { prefer: 'type-imports', fixStyle: 'separate-type-imports' },
            ],
            'no-console': ['error', { allow: ['warn', 'error'] }],
        },
    },
    {
        // The browser extension is plain scripts, not part of the Vue app.
        files: ['extension/**/*.js'],
        languageOptions: {
            sourceType: 'script',
            globals: {
                globalThis: 'readonly',
                document: 'readonly',
                navigator: 'readonly',
                window: 'readonly',
                fetch: 'readonly',
                URL: 'readonly',
                File: 'readonly',
                Event: 'readonly',
                DataTransfer: 'readonly',
                HTMLTextAreaElement: 'readonly',
                HTMLInputElement: 'readonly',
                Uint8Array: 'readonly',
                atob: 'readonly',
                btoa: 'readonly',
                chrome: 'readonly',
            },
        },
    },
    {
        files: ['extension/**/*.test.js'],
        languageOptions: {
            sourceType: 'module',
            globals: { HTMLElement: 'readonly', process: 'readonly' },
        },
    },
    prettier,
);
