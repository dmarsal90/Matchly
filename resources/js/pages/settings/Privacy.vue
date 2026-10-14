<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { privacy } from '@/routes/legal';
import { consent, edit, exportMethod as exportData } from '@/routes/privacy';
import { edit as editAccount } from '@/routes/profile';
import { Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

type Consent = { purpose: string; granted: boolean; granted_at: string | null };

defineProps<{ consents: Consent[]; policyVersion: string }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Privacy', href: edit() }],
    },
});

function toggle(item: Consent, granted: boolean): void {
    if (
        !granted &&
        !window.confirm(
            trans(`privacy.purposes.${item.purpose}.revoke_warning`),
        )
    ) {
        return;
    }

    router.put(
        consent.url(),
        { purpose: item.purpose, granted },
        { preserveScroll: true },
    );
}

const formatDate = (iso: string): string =>
    new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
        dateStyle: 'medium',
    }).format(new Date(iso));
</script>

<template>
    <Head :title="trans('privacy.title')" />

    <div class="space-y-10">
        <section class="space-y-6">
            <Heading
                variant="small"
                :title="trans('privacy.consents_title')"
                :description="trans('privacy.consents_description')"
            />

            <ul class="divide-y rounded-lg border">
                <li
                    v-for="item in consents"
                    :key="item.purpose"
                    class="flex items-start gap-4 p-4"
                >
                    <div class="min-w-0 flex-1 space-y-1">
                        <p
                            :id="`consent-${item.purpose}`"
                            class="text-sm font-medium"
                        >
                            {{
                                trans(`privacy.purposes.${item.purpose}.title`)
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                trans(
                                    `privacy.purposes.${item.purpose}.description`,
                                )
                            }}
                        </p>
                        <p
                            v-if="item.granted && item.granted_at"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                trans('privacy.granted_since', {
                                    date: formatDate(item.granted_at),
                                })
                            }}
                        </p>
                    </div>
                    <Switch
                        :model-value="item.granted"
                        :aria-labelledby="`consent-${item.purpose}`"
                        @update:model-value="toggle(item, $event)"
                    />
                </li>
            </ul>

            <p class="text-xs text-muted-foreground">
                <Link :href="privacy()" class="underline underline-offset-4">{{
                    trans('privacy.policy_link')
                }}</Link>
                · {{ trans('privacy.version', { version: policyVersion }) }}
            </p>
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                :title="trans('privacy.export_title')"
                :description="trans('privacy.export_description')"
            />
            <!-- A plain link: the response is a file download, not an Inertia page. -->
            <Button as="a" variant="outline" :href="exportData.url()">{{
                trans('privacy.export_button')
            }}</Button>
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                :title="trans('privacy.delete_title')"
                :description="trans('privacy.delete_description')"
            />
            <Button as-child variant="destructive">
                <Link :href="editAccount()">{{
                    trans('privacy.delete_link')
                }}</Link>
            </Button>
        </section>
    </div>
</template>
