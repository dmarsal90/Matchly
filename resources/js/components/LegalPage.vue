<script setup lang="ts">
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

/** Renders a legal document whose texts live in lang/{locale}/legal.php. */
const props = defineProps<{
    document: 'privacy' | 'terms';
    sections: string[];
    replacements?: Record<string, string>;
}>();

const page = usePage();
const values = (): Record<string, string> => ({
    app: page.props.name,
    ...props.replacements,
});
</script>

<template>
    <Head :title="trans(`legal.${document}.title`)" />

    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <PublicHeader />

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-10 sm:px-6">
            <article class="space-y-8">
                <header class="space-y-3">
                    <h1 class="text-3xl font-semibold tracking-tight">
                        {{ trans(`legal.${document}.title`) }}
                    </h1>
                    <p class="text-lg text-muted-foreground">
                        {{ trans(`legal.${document}.intro`, values()) }}
                    </p>
                </header>

                <section
                    v-for="section in sections"
                    :key="section"
                    class="space-y-2"
                >
                    <h2 class="text-lg font-semibold">
                        {{
                            trans(`legal.${document}.sections.${section}.title`)
                        }}
                    </h2>
                    <p class="leading-relaxed text-muted-foreground">
                        {{
                            trans(
                                `legal.${document}.sections.${section}.body`,
                                values(),
                            )
                        }}
                    </p>
                </section>
            </article>
        </main>

        <PublicFooter />
    </div>
</template>
