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
    <Head :title="trans('app.tagline')">
        <meta
            head-key="og:title"
            property="og:title"
            :content="trans('app.tagline')"
        />
    </Head>

    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <PublicHeader />

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 sm:px-6">
            <section
                    class="grid items-center gap-10 py-12 md:grid-cols-[1.15fr_0.85fr] md:py-20"
            >
                <div>
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ trans('app.tagline') }}
                    </p>
                    <h1
                        class="mt-3 max-w-3xl text-4xl leading-[1.08] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl"
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
                        <Button as-child size="lg" class="bg-[#F47761] text-[#292D32] hover:bg-[#e96953]">
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

                <!-- Brand layers: opportunity, fit and application. Decorative. -->
                <div class="mx-auto flex aspect-square w-full max-w-[19rem] items-center justify-center rounded-[2.5rem] bg-[#292D32] p-12 shadow-[0_24px_60px_rgb(41_45_50/12%)] sm:p-14">
                    <svg viewBox="0 0 120 120" class="size-full" fill="none" aria-hidden="true">
                        <path d="m60 12 47 25-47 25-47-25 47-25Z" fill="#F47761" />
                        <path d="m13 53 47 25 47-25M13 76l47 25 47-25" stroke="#F47761" stroke-width="11" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </section>

            <section
                class="grid gap-4 border-t py-8 sm:grid-cols-3 sm:gap-5 sm:py-10"
                :aria-label="trans('app.how_it_works')"
            >
                <div v-for="(point, index) in points" :key="point" class="rounded-xl border bg-card p-5 sm:p-6">
                    <span class="mb-4 flex size-8 items-center justify-center rounded-lg bg-secondary text-sm font-semibold text-secondary-foreground">{{ String(index + 1).padStart(2, '0') }}</span>
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
