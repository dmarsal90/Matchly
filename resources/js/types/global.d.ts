import type { Auth } from '@/types/auth';
import type { Directive } from 'vue';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: string;
            supportedLocales: string[];
            oauthProviders: ('google' | 'github')[];
            notifications: { unread: number } | null;
            auth: Auth;
            sidebarOpen: boolean;
            /** Public Reverb settings, sent at runtime; null when real time is off. */
            reverb: {
                key: string;
                host: string;
                port: number;
                scheme: 'http' | 'https';
            } | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
