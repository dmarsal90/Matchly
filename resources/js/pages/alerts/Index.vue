<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';
import { filterChips } from '@/features/alerts/lib/summary';
import type { SavedAlert } from '@/features/alerts/types';
import { relativeTime } from '@/features/jobs/lib/format';
import { destroy, index, update } from '@/routes/alerts';
import { index as jobsIndex } from '@/routes/jobs';
import { Head, Link, router } from '@inertiajs/vue3';
import { BellRing, Mail, MailX, Pause, Play, Trash2 } from '@lucide/vue';
import { getActiveLanguage, trans, transChoice } from 'laravel-vue-i18n';

const props = defineProps<{
    alerts: SavedAlert[];
    emailConsented: boolean;
    frequencies: string[];
    maxAlerts: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Alerts', href: index() }] },
});

function change(
    alert: SavedAlert,
    changes: Partial<
        Record<'frequency' | 'email' | 'active', string | boolean>
    >,
): void {
    router.put(update.url(alert.id), changes, { preserveScroll: true });
}

function toggleEmail(alert: SavedAlert): void {
    // Turning e-mail on for the first time records the consent asked for when the alert was created.
    change(alert, {
        email: !alert.email,
        ...(props.emailConsented || alert.email ? {} : { email_consent: true }),
    });
}

function remove(alert: SavedAlert): void {
    if (window.confirm(trans('alerts.item.delete_confirm'))) {
        router.delete(destroy.url(alert.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="trans('alerts.title')" />

    <div class="mx-auto w-full max-w-4xl space-y-6 px-4 py-6">
        <Heading
            :title="trans('alerts.title')"
            :description="trans('alerts.description')"
        />

        <EmptyState
            v-if="alerts.length === 0"
            :icon="BellRing"
            :title="trans('alerts.empty.title')"
            :description="trans('alerts.empty.description')"
        >
            <Button size="sm" as-child>
                <Link :href="jobsIndex()">{{
                    trans('alerts.empty.action')
                }}</Link>
            </Button>
        </EmptyState>

        <ul v-else class="space-y-3">
            <li
                v-for="alert in alerts"
                :key="alert.id"
                class="space-y-3 rounded-xl border p-4"
                :class="!alert.active && 'opacity-70'"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium">{{ alert.name }}</h2>
                            <Badge
                                :variant="
                                    alert.active ? 'secondary' : 'outline'
                                "
                                >{{
                                    alert.active
                                        ? trans('alerts.item.active')
                                        : trans('alerts.item.paused')
                                }}</Badge
                            >
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                transChoice(
                                    'alerts.item.matches',
                                    alert.matches,
                                )
                            }}
                            <template v-if="alert.pending">
                                ·
                                {{
                                    transChoice(
                                        'alerts.item.pending',
                                        alert.pending,
                                    )
                                }}</template
                            >
                            <template v-if="alert.last_notified_at">
                                ·
                                {{
                                    trans('alerts.item.last_notified', {
                                        time: relativeTime(
                                            alert.last_notified_at,
                                            getActiveLanguage(),
                                        ),
                                    })
                                }}</template
                            >
                        </p>
                    </div>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="jobsIndex({ query: alert.filters })">{{
                            trans('alerts.item.open')
                        }}</Link>
                    </Button>
                </div>

                <ul
                    v-if="filterChips(alert.filters).length"
                    class="flex flex-wrap gap-1.5"
                >
                    <li
                        v-for="chip in filterChips(alert.filters)"
                        :key="chip"
                        class="rounded-full border px-2 py-0.5 text-xs text-muted-foreground"
                    >
                        {{ chip }}
                    </li>
                </ul>
                <p v-else class="text-xs text-muted-foreground">
                    {{ trans('alerts.form.no_filters') }}
                </p>

                <div class="flex flex-wrap items-center gap-3 border-t pt-3">
                    <NativeSelect
                        :model-value="alert.frequency"
                        class="h-8 w-auto text-sm"
                        :aria-label="trans('alerts.form.frequency')"
                        @update:model-value="
                            change(alert, { frequency: String($event) })
                        "
                    >
                        <option
                            v-for="value in frequencies"
                            :key="value"
                            :value="value"
                        >
                            {{ trans(`alerts.frequencies.${value}`) }}
                        </option>
                    </NativeSelect>

                    <label class="flex items-center gap-2 text-sm">
                        <Switch
                            :model-value="alert.email"
                            @update:model-value="toggleEmail(alert)"
                        />
                        <Mail
                            v-if="alert.email"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <MailX v-else class="size-4" aria-hidden="true" />
                        {{
                            alert.email
                                ? trans('alerts.item.email_on')
                                : trans('alerts.item.email_off')
                        }}
                    </label>

                    <div class="ml-auto flex gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="change(alert, { active: !alert.active })"
                        >
                            <Pause
                                v-if="alert.active"
                                class="size-4"
                                aria-hidden="true"
                            />
                            <Play v-else class="size-4" aria-hidden="true" />
                            {{
                                alert.active
                                    ? trans('alerts.item.pause')
                                    : trans('alerts.item.resume')
                            }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="remove(alert)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                            {{ trans('alerts.item.delete') }}
                        </Button>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
