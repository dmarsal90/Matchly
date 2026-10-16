<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { destroy, index } from '@/routes/radar';
import { index as jobsIndex } from '@/routes/jobs';
import { Head, Link, router } from '@inertiajs/vue3';
import { Radar } from '@lucide/vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';

defineProps<{
    companies: {
        id: number;
        company: string;
        followed_at: string;
        last_notified_at: string | null;
    }[];
    max: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'nav.radar', href: index() }] },
});

const date = (value: string): string =>
    new Date(value).toLocaleDateString(getActiveLanguage());
</script>

<template>
    <Head :title="trans('radar.title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6">
        <Heading
            :title="trans('radar.title')"
            :description="trans('radar.description')"
        />

        <EmptyState
            v-if="companies.length === 0"
            :icon="Radar"
            :title="trans('radar.empty')"
        />

        <ul
            v-else
            class="divide-y rounded-xl border"
            :aria-label="trans('radar.list')"
        >
            <li
                v-for="item in companies"
                :key="item.id"
                class="flex flex-wrap items-center justify-between gap-2 p-3 text-sm"
            >
                <div>
                    <p class="font-medium">{{ item.company }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            trans('radar.since', {
                                date: date(item.followed_at),
                            })
                        }}
                        ·
                        {{
                            item.last_notified_at
                                ? trans('radar.last', {
                                      date: date(item.last_notified_at),
                                  })
                                : trans('radar.never')
                        }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link
                            :href="jobsIndex({ query: { q: item.company } })"
                            >{{ trans('radar.search') }}</Link
                        >
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        :aria-label="`${trans('radar.unfollow')}: ${item.company}`"
                        @click="
                            router.delete(destroy.url(item.id), {
                                preserveScroll: true,
                            })
                        "
                        >{{ trans('radar.unfollow') }}</Button
                    >
                </div>
            </li>
        </ul>
    </div>
</template>
