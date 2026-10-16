<script setup lang="ts">
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Button } from '@/components/ui/button';
import { dashboard, register } from '@/routes';
import { index as jobs } from '@/routes/jobs';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

const page = usePage();
const points = ['collect', 'match', 'prepare'] as const;
</script>

<template>
    <Head :title="trans('app.tagline')" />

    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <PublicHeader />

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 sm:px-6">
            <section
                class="grid items-center gap-10 py-14 md:grid-cols-[1.15fr_0.85fr] md:py-24"
            >
                <div>
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ trans('app.tagline') }}
                    </p>
                    <h1
                        class="mt-3 text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl"
                    >
                        {{ trans('app.hero_title') }}
                    </h1>
                    <p
                        class="mt-5 max-w-xl text-lg text-pretty text-muted-foreground"
                    >
                        {{
                            trans('app.hero_subtitle', { app: page.props.name })
                        }}
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Button as-child size="lg">
                            <Link
                                :href="
                                    page.props.auth.user
                                        ? dashboard()
                                        : register()
                                "
                            >
                                {{ trans('app.get_started') }}
                            </Link>
                        </Button>
                        <Button as-child size="lg" variant="outline">
                            <Link :href="jobs()">{{
                                trans('app.browse_jobs')
                            }}</Link>
                        </Button>
                    </div>
                </div>

                <!-- The match: where a profile and a job overlap. Decorative. -->
                <svg
                    viewBox="0 0 320 240"
                    class="mx-auto w-full max-w-sm text-primary"
                    fill="none"
                    aria-hidden="true"
                >
                    <circle
                        cx="120"
                        cy="120"
                        r="92"
                        fill="currentColor"
                        fill-opacity=".08"
                        stroke="currentColor"
                        stroke-width="3"
                    />
                    <circle
                        cx="200"
                        cy="120"
                        r="92"
                        fill="currentColor"
                        fill-opacity=".08"
                        stroke="currentColor"
                        stroke-width="3"
                    />
                    <path
                        d="M160 41.7A92 92 0 0 1 160 198.3A92 92 0 0 1 160 41.7Z"
                        fill="var(--highlight)"
                    />
                </svg>
            </section>

            <section
                class="grid gap-8 border-t py-10 sm:grid-cols-3"
                :aria-label="trans('app.how_it_works')"
            >
                <div v-for="point in points" :key="point">
                    <h2 class="text-base font-semibold">
                        {{ trans(`app.points.${point}.title`) }}
                    </h2>
                    <p class="mt-1.5 text-sm text-pretty text-muted-foreground">
                        {{ trans(`app.points.${point}.text`) }}
                    </p>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
