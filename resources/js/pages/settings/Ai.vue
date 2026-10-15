<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/ai';
import { destroy, update } from '@/routes/ai/key';
import { edit as editPrivacy } from '@/routes/privacy';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CircleCheck, CircleSlash } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';

defineProps<{
    hasOwnKey: boolean;
    keyHint: string | null;
    available: boolean;
    consented: boolean;
    usage: {
        calls: number;
        cost_usd: number;
        own_key_cost_usd: number;
        budget_usd: number;
        budget_used_usd: number;
        by_task: Record<string, number>;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.ai', href: edit() }],
    },
});

const form = useForm({ anthropic_api_key: '' });

function save(): void {
    form.put(update.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(): void {
    if (window.confirm(trans('ai.key.remove_confirm'))) {
        router.delete(destroy.url(), { preserveScroll: true });
    }
}

const usd = (value: number): string => value.toFixed(2);
</script>

<template>
    <Head :title="trans('ai.title')" />

    <div class="space-y-10">
        <section class="space-y-4">
            <Heading
                variant="small"
                :title="trans('ai.title')"
                :description="trans('ai.description')"
            />
            <p class="flex items-center gap-2 text-sm">
                <CircleCheck
                    v-if="available"
                    class="size-4 text-emerald-600"
                    aria-hidden="true"
                />
                <CircleSlash
                    v-else
                    class="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                {{
                    available
                        ? trans('ai.status.available')
                        : trans('ai.status.unavailable')
                }}
            </p>
            <p class="text-sm text-muted-foreground">
                {{
                    consented
                        ? trans('ai.consent.given')
                        : trans('ai.consent.missing')
                }}
                <Link
                    :href="editPrivacy()"
                    class="font-medium text-foreground underline underline-offset-2"
                    >{{ trans('ai.consent.manage') }}</Link
                >
            </p>
        </section>

        <section class="space-y-3">
            <Heading variant="small" :title="trans('ai.usage.title')" />
            <p class="text-sm">
                {{ transChoice('ai.usage.calls', usage.calls) }}
            </p>
            <div>
                <p class="mb-1 text-sm text-muted-foreground">
                    {{
                        trans('ai.usage.budget', {
                            used: usd(usage.budget_used_usd),
                            limit: usd(usage.budget_usd),
                        })
                    }}
                </p>
                <div
                    class="h-2 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    :aria-valuenow="Math.round(usage.budget_used_usd * 100)"
                    aria-valuemin="0"
                    :aria-valuemax="Math.round(usage.budget_usd * 100)"
                    :aria-label="trans('ai.usage.title')"
                >
                    <span
                        class="block h-full rounded-full bg-primary"
                        :style="{
                            width: `${Math.min(100, usage.budget_usd > 0 ? (usage.budget_used_usd / usage.budget_usd) * 100 : 0)}%`,
                        }"
                    />
                </div>
            </div>
            <p
                v-if="usage.own_key_cost_usd > 0"
                class="text-sm text-muted-foreground"
            >
                {{
                    trans('ai.usage.own_key_cost', {
                        cost: usd(usage.own_key_cost_usd),
                    })
                }}
            </p>
            <ul
                v-if="Object.keys(usage.by_task).length"
                class="text-sm text-muted-foreground"
            >
                <li v-for="(count, task) in usage.by_task" :key="task">
                    {{ trans(`ai.usage.tasks.${task}`) }}: {{ count }}
                </li>
            </ul>
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                :title="trans('ai.key.title')"
                :description="trans('ai.key.description')"
            />
            <div
                v-if="hasOwnKey"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3 text-sm"
            >
                <span>{{
                    trans('ai.key.current', { hint: keyHint ?? '' })
                }}</span>
                <Button variant="outline" size="sm" @click="remove">{{
                    trans('ai.key.remove')
                }}</Button>
            </div>
            <form class="grid max-w-md gap-2" @submit.prevent="save">
                <Label for="anthropic-key">{{ trans('ai.key.label') }}</Label>
                <Input
                    id="anthropic-key"
                    v-model="form.anthropic_api_key"
                    type="password"
                    autocomplete="off"
                    spellcheck="false"
                    :placeholder="trans('ai.key.placeholder')"
                    required
                />
                <InputError :message="form.errors.anthropic_api_key" />
                <div>
                    <Button type="submit" :disabled="form.processing">{{
                        trans('ai.key.save')
                    }}</Button>
                </div>
            </form>
        </section>
    </div>
</template>
