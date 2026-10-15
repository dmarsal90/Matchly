<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { index as alertsIndex } from '@/routes/alerts';
import { edit, update } from '@/routes/notification-preferences';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';

const props = defineProps<{
    email: boolean;
    realtime: boolean;
    alerts: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'nav.notifications', href: edit() }] },
});

const form = useForm({ email: props.email, realtime: props.realtime });

function save(): void {
    form.put(update.url(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="trans('alerts.preferences.title')" />

    <form class="space-y-6" @submit.prevent="save">
        <Heading
            variant="small"
            :title="trans('alerts.preferences.title')"
            :description="trans('alerts.preferences.description')"
        />

        <ul class="divide-y rounded-lg border">
            <li class="flex items-start gap-4 p-4">
                <div class="min-w-0 flex-1 space-y-1">
                    <p id="pref-email" class="text-sm font-medium">
                        {{ trans('alerts.preferences.email') }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ trans('alerts.preferences.email_hint') }}
                    </p>
                </div>
                <Switch v-model="form.email" aria-labelledby="pref-email" />
            </li>
            <li class="flex items-start gap-4 p-4">
                <div class="min-w-0 flex-1 space-y-1">
                    <p id="pref-realtime" class="text-sm font-medium">
                        {{ trans('alerts.preferences.realtime') }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ trans('alerts.preferences.realtime_hint') }}
                    </p>
                </div>
                <Switch
                    v-model="form.realtime"
                    aria-labelledby="pref-realtime"
                />
            </li>
        </ul>

        <p class="text-sm text-muted-foreground">
            {{ transChoice('alerts.preferences.alerts', alerts) }}
            <Link
                :href="alertsIndex()"
                class="font-medium text-foreground underline underline-offset-2"
                >{{ trans('alerts.preferences.manage_alerts') }}</Link
            >
        </p>

        <Button type="submit" :disabled="form.processing">{{
            trans('alerts.preferences.save')
        }}</Button>
    </form>
</template>
