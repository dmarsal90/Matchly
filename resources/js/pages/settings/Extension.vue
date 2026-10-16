<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getAppName } from '@/lib/brand';
import { destroy, edit, store } from '@/routes/extension';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';

defineProps<{
    tokens: {
        id: number;
        name: string;
        last_used_at: string | null;
        created_at: string;
    }[];
    baseUrl: string;
    newToken: string | null;
    max: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.extension', href: edit() }],
    },
});

const app = getAppName();
const form = useForm({ name: '' });

function create(): void {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function revoke(id: number): void {
    router.delete(destroy.url(id), { preserveScroll: true });
}

const date = (value: string): string =>
    new Date(value).toLocaleDateString(getActiveLanguage());
</script>

<template>
    <Head :title="trans('extension.title')" />

    <div class="space-y-10">
        <section class="space-y-4">
            <Heading
                variant="small"
                :title="trans('extension.title')"
                :description="trans('extension.desc')"
            />

            <ol class="list-decimal space-y-1 pl-5 text-sm">
                <li>{{ trans('extension.step1') }}</li>
                <li>{{ trans('extension.step2', { app }) }}</li>
                <li>{{ trans('extension.step3') }}</li>
                <li>{{ trans('extension.step4') }}</li>
            </ol>
            <p class="text-sm">
                {{ trans('extension.url', { app }) }}:
                <code class="rounded bg-muted px-1.5 py-0.5">{{
                    baseUrl
                }}</code>
            </p>
        </section>

        <section
            v-if="newToken"
            class="space-y-2 rounded-xl border border-primary/40 bg-primary/5 p-4"
            role="status"
        >
            <h3 class="font-medium">{{ trans('extension.new_title') }}</h3>
            <code class="block rounded bg-muted p-2 text-sm break-all">{{
                newToken
            }}</code>
            <p class="text-xs text-muted-foreground">
                {{ trans('extension.new_hint', { app }) }}
            </p>
        </section>

        <section class="space-y-4" aria-labelledby="extension-tokens">
            <h3 id="extension-tokens" class="font-medium">
                {{ trans('extension.tokens') }}
            </h3>
            <p v-if="!tokens.length" class="text-sm text-muted-foreground">
                {{ trans('extension.none') }}
            </p>
            <ul v-else class="divide-y rounded-xl border">
                <li
                    v-for="token in tokens"
                    :key="token.id"
                    class="flex flex-wrap items-center justify-between gap-2 p-3 text-sm"
                >
                    <div>
                        <p class="font-medium">{{ token.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                trans('extension.created', {
                                    date: date(token.created_at),
                                })
                            }}
                            ·
                            {{
                                token.last_used_at
                                    ? trans('extension.last', {
                                          date: date(token.last_used_at),
                                      })
                                    : trans('extension.never')
                            }}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        :aria-label="`${trans('extension.revoke')}: ${token.name}`"
                        @click="revoke(token.id)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        {{ trans('extension.revoke') }}
                    </Button>
                </li>
            </ul>

            <form
                v-if="tokens.length < max"
                class="grid max-w-sm gap-2"
                @submit.prevent="create"
            >
                <Label for="token-name">{{ trans('extension.name') }}</Label>
                <Input
                    id="token-name"
                    v-model="form.name"
                    maxlength="60"
                    :placeholder="trans('extension.ph')"
                    required
                />
                <InputError :message="form.errors.name" />
                <div>
                    <Button type="submit" :disabled="form.processing">{{
                        trans('extension.create')
                    }}</Button>
                </div>
            </form>
        </section>
    </div>
</template>
