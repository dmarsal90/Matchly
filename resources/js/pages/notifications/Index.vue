<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    notificationHref,
    notificationSummary,
    notificationTitle,
} from '@/features/alerts/lib/notification';
import type { AppNotification } from '@/features/alerts/types';
import { relativeTime } from '@/features/jobs/lib/format';
import { show as showJob } from '@/routes/jobs';
import { index, read, readAll } from '@/routes/notifications';
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    notifications: {
        data: AppNotification[];
        meta: { current_page: number; last_page: number; total: number };
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'nav.notifications', href: index() }] },
});

const hasUnread = computed(() =>
    props.notifications.data.some((item) => !item.read_at),
);

function markRead(item: AppNotification): void {
    router.post(read.url(item.id), {}, { preserveScroll: true });
}

function goToPage(page: number): void {
    router.get(index.url(), page > 1 ? { page } : {}, {
        preserveScroll: false,
    });
}
</script>

<template>
    <Head :title="trans('alerts.notifications.title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading :title="trans('alerts.notifications.title')" />
            <Button
                v-if="hasUnread"
                variant="outline"
                size="sm"
                @click="
                    router.post(readAll.url(), {}, { preserveScroll: true })
                "
            >
                {{ trans('alerts.notifications.mark_all_read') }}
            </Button>
        </div>

        <EmptyState
            v-if="notifications.data.length === 0"
            :icon="Bell"
            :title="trans('alerts.notifications.empty')"
        />

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="item in notifications.data"
                :key="item.id"
                class="space-y-2 p-4"
                :class="!item.read_at && 'bg-primary/5'"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p :class="item.read_at ? '' : 'font-medium'">
                            {{ notificationTitle(item.data) }}
                        </p>
                        <p
                            v-if="item.created_at"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                relativeTime(
                                    item.created_at,
                                    getActiveLanguage(),
                                )
                            }}
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="notificationHref(item.data)">{{
                                trans('alerts.notifications.open')
                            }}</Link>
                        </Button>
                        <Button
                            v-if="!item.read_at"
                            variant="ghost"
                            size="sm"
                            @click="markRead(item)"
                        >
                            {{ trans('alerts.notifications.mark_read') }}
                        </Button>
                    </div>
                </div>
                <p
                    v-if="item.data.type !== 'job_alert'"
                    class="text-sm text-muted-foreground"
                >
                    {{ notificationSummary(item.data) }}
                </p>
                <ul v-else class="space-y-0.5 text-sm">
                    <li v-for="job in item.data.jobs" :key="job.id">
                        <Link
                            :href="showJob({ job: job.id })"
                            class="hover:underline"
                            >{{ job.title }}</Link
                        >
                        <span class="text-muted-foreground">
                            · {{ job.company }}</span
                        >
                    </li>
                </ul>
            </li>
        </ul>

        <Pagination
            v-if="notifications.meta.last_page > 1"
            :current-page="notifications.meta.current_page"
            :last-page="notifications.meta.last_page"
            @page="goToPage"
        />
    </div>
</template>
