<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    notificationHref,
    notificationSummary,
    notificationTitle,
} from '@/features/alerts/lib/notification';
import type {
    AppNotification,
    DeadlineData,
    FollowUpData,
    JobAlertData,
    RadarData,
} from '@/features/alerts/types';
import { relativeTime } from '@/features/jobs/lib/format';
import { index as jobsIndex } from '@/routes/jobs';
import {
    index as notificationsIndex,
    latest,
    read,
    readAll,
} from '@/routes/notifications';
import { Link, router, usePage } from '@inertiajs/vue3';
import { echoIsConfigured, useEchoNotification } from '@laravel/echo-vue';
import { Bell } from '@lucide/vue';
import { getActiveLanguage, trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage();
const userId = computed(() => page.props.auth.user?.id);
const unread = ref(page.props.notifications?.unread ?? 0);
const items = ref<AppNotification[]>([]);
const loading = ref(false);

watch(
    () => page.props.notifications?.unread,
    (value) => (unread.value = value ?? 0),
);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const response = await fetch(latest.url(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const json = (await response.json()) as {
            unread: number;
            notifications: AppNotification[];
        };
        items.value = json.notifications;
        unread.value = json.unread;
    } finally {
        loading.value = false;
    }
}

function open(item: AppNotification): void {
    if (!item.read_at) {
        router.post(
            read.url(item.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['notifications'],
            },
        );
    }
    router.visit(notificationHref(item.data));
}

function markAllRead(): void {
    router.post(
        readAll.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['notifications'],
            onSuccess: () => {
                items.value = items.value.map((item) => ({
                    ...item,
                    read_at: item.read_at ?? new Date().toISOString(),
                }));
            },
        },
    );
}

// New matching jobs arrive over the private channel while the app is open
// (when the server has real time on; otherwise the count updates per visit).
if (userId.value && echoIsConfigured()) {
    useEchoNotification<JobAlertData>(
        `users.${userId.value}`,
        (payload) => {
            unread.value++;
            items.value = [
                {
                    id: payload.id,
                    data: payload,
                    read_at: null,
                    created_at: new Date().toISOString(),
                },
                ...items.value,
            ].slice(0, 8);
            toast.info(
                transChoice('alerts.notifications.live', payload.count, {
                    name: payload.alert_name,
                }),
                {
                    action: {
                        label: trans('alerts.notifications.open'),
                        onClick: () =>
                            router.visit(jobsIndex({ query: payload.filters })),
                    },
                },
            );
        },
        'job.alert',
    );

    useEchoNotification<FollowUpData>(
        `users.${userId.value}`,
        (payload) => {
            unread.value++;
            items.value = [
                {
                    id: payload.id,
                    data: payload,
                    read_at: null,
                    created_at: new Date().toISOString(),
                },
                ...items.value,
            ].slice(0, 8);
            toast.info(notificationTitle(payload), {
                description: notificationSummary(payload),
                action: {
                    label: trans('alerts.notifications.open'),
                    onClick: () => router.visit(notificationHref(payload)),
                },
            });
        },
        'application.follow_up',
    );

    useEchoNotification<DeadlineData>(
        `users.${userId.value}`,
        (payload) => {
            unread.value++;
            items.value = [
                {
                    id: payload.id,
                    data: payload,
                    read_at: null,
                    created_at: new Date().toISOString(),
                },
                ...items.value,
            ].slice(0, 8);
            toast.info(notificationTitle(payload), {
                description: notificationSummary(payload),
                action: {
                    label: trans('alerts.notifications.open'),
                    onClick: () => router.visit(notificationHref(payload)),
                },
            });
        },
        'application.deadline',
    );

    useEchoNotification<RadarData>(
        `users.${userId.value}`,
        (payload) => {
            unread.value++;
            items.value = [
                {
                    id: payload.id,
                    data: payload,
                    read_at: null,
                    created_at: new Date().toISOString(),
                },
                ...items.value,
            ].slice(0, 8);
            toast.info(notificationTitle(payload), {
                description: notificationSummary(payload),
                action: {
                    label: trans('alerts.notifications.open'),
                    onClick: () => router.visit(notificationHref(payload)),
                },
            });
        },
        'company.radar',
    );
}
</script>

<template>
    <DropdownMenu @update:open="(value: boolean) => value && load()">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative"
                :aria-label="`${trans('alerts.notifications.bell')}: ${transChoice('alerts.notifications.unread', unread)}`"
            >
                <Bell class="size-5" aria-hidden="true" />
                <span
                    v-if="unread > 0"
                    class="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground"
                    aria-hidden="true"
                    >{{ unread > 99 ? '99+' : unread }}</span
                >
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 p-0">
            <div class="flex items-center justify-between border-b px-3 py-2">
                <p class="text-sm font-medium">
                    {{ trans('alerts.notifications.title') }}
                </p>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground"
                    @click="markAllRead"
                >
                    {{ trans('alerts.notifications.mark_all_read') }}
                </button>
            </div>
            <p
                v-if="!loading && items.length === 0"
                class="px-3 py-6 text-center text-sm text-muted-foreground"
            >
                {{ trans('alerts.notifications.empty') }}
            </p>
            <ul v-else class="max-h-96 divide-y overflow-y-auto">
                <li v-for="item in items" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm hover:bg-accent"
                        @click="open(item)"
                    >
                        <span class="flex w-full items-center gap-2">
                            <span
                                v-if="!item.read_at"
                                class="size-2 shrink-0 rounded-full bg-primary"
                                aria-hidden="true"
                            />
                            <span
                                :class="
                                    item.read_at
                                        ? 'text-muted-foreground'
                                        : 'font-medium'
                                "
                                >{{ notificationTitle(item.data) }}</span
                            >
                        </span>
                        <span
                            class="line-clamp-1 text-xs text-muted-foreground"
                        >
                            {{ notificationSummary(item.data) }}
                        </span>
                        <span
                            v-if="item.created_at"
                            class="text-[11px] text-muted-foreground"
                            >{{
                                relativeTime(
                                    item.created_at,
                                    getActiveLanguage(),
                                )
                            }}</span
                        >
                    </button>
                </li>
            </ul>
            <div class="border-t px-3 py-2 text-center">
                <Link
                    :href="notificationsIndex()"
                    class="text-xs font-medium hover:underline"
                    >{{ trans('alerts.notifications.view_all') }}</Link
                >
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
