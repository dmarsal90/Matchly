<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { approve, destroy, discover, store } from '@/routes/company-notes';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ExternalLink, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import type { CompanyNoteEntry } from '../types';

const props = defineProps<{ company: string; notes: CompanyNoteEntry[] }>();

const form = useForm({ company: props.company, fact: '', source_url: '' });

const pending = computed(() => props.notes.filter((note) => !note.approved));
const ready = computed(() => props.notes.filter((note) => note.approved));

const lookup = useForm({ company: props.company, site_url: '' });

function find(): void {
    lookup.post(discover.url(), {
        preserveScroll: true,
        only: ['companyNotes'],
    });
}

function accept(note: CompanyNoteEntry): void {
    router.post(
        approve.url(note.id),
        {},
        { preserveScroll: true, only: ['companyNotes'] },
    );
}

function add(): void {
    form.post(store.url(), {
        preserveScroll: true,
        only: ['companyNotes'],
        onSuccess: () => form.reset('fact', 'source_url'),
    });
}

function remove(note: CompanyNoteEntry): void {
    if (window.confirm(trans('company_notes.delete_confirm'))) {
        router.delete(destroy.url(note.id), {
            preserveScroll: true,
            only: ['companyNotes'],
        });
    }
}
</script>

<template>
    <section
        class="space-y-3 rounded-xl border p-4"
        aria-labelledby="company-notes"
    >
        <div>
            <h2 id="company-notes" class="font-semibold">
                {{ trans('company_notes.title', { company }) }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ trans('company_notes.description') }}
            </p>
        </div>

        <div v-if="pending.length" class="space-y-2">
            <h3 class="text-sm font-medium">
                {{ trans('company_notes.suggestions') }}
            </h3>
            <p class="text-xs text-muted-foreground">
                {{ trans('company_notes.suggestions_hint') }}
            </p>
            <ul class="space-y-2">
                <li
                    v-for="note in pending"
                    :key="note.id"
                    class="space-y-2 rounded-lg border border-dashed p-3 text-sm"
                >
                    <p>{{ note.fact }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            v-if="note.source_url"
                            :href="note.source_url"
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            class="inline-flex items-center gap-1 text-xs underline underline-offset-2"
                        >
                            {{ trans('company_notes.source_link') }}
                            <ExternalLink class="size-3" aria-hidden="true" />
                        </a>
                        <Button size="sm" @click="accept(note)">{{
                            trans('company_notes.approve')
                        }}</Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="remove(note)"
                            >{{ trans('company_notes.discard') }}</Button
                        >
                    </div>
                </li>
            </ul>
        </div>

        <ul
            v-if="ready.length"
            class="space-y-2"
            :aria-label="trans('company_notes.approved_title')"
        >
            <li
                v-for="note in ready"
                :key="note.id"
                class="flex items-start justify-between gap-2 rounded-lg border bg-muted/30 p-3 text-sm"
            >
                <div class="space-y-1">
                    <p>{{ note.fact }}</p>
                    <p
                        class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Badge variant="outline">{{
                            trans(`company_notes.origin.${note.origin}`)
                        }}</Badge>
                        <a
                            v-if="note.source_url"
                            :href="note.source_url"
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            class="inline-flex items-center gap-1 underline underline-offset-2"
                        >
                            {{ trans('company_notes.source_link') }}
                            <ExternalLink class="size-3" aria-hidden="true" />
                        </a>
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    :aria-label="trans('company_notes.delete')"
                    :title="trans('company_notes.delete')"
                    @click="remove(note)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </Button>
            </li>
        </ul>
        <p v-else-if="!pending.length" class="text-sm text-muted-foreground">
            {{ trans('company_notes.empty') }}
        </p>

        <form class="grid gap-2 rounded-lg border p-3" @submit.prevent="find">
            <h3 class="text-sm font-medium">
                {{ trans('company_notes.discover.title') }}
            </h3>
            <p class="text-xs text-muted-foreground">
                {{ trans('company_notes.discover.hint') }}
            </p>
            <div class="grid gap-1">
                <Label for="company-site" class="text-xs">{{
                    trans('company_notes.discover.url')
                }}</Label>
                <input
                    id="company-site"
                    v-model="lookup.site_url"
                    type="url"
                    required
                    :placeholder="
                        trans('company_notes.discover.url_placeholder')
                    "
                    class="h-8 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="lookup.errors.site_url" />
            </div>
            <Button
                type="submit"
                size="sm"
                variant="outline"
                class="justify-self-start"
                :disabled="lookup.processing"
                >{{ trans('company_notes.discover.submit') }}</Button
            >
        </form>

        <form class="grid gap-2" @submit.prevent="add">
            <div class="grid gap-1">
                <Label for="company-note-fact" class="text-xs">{{
                    trans('company_notes.fact')
                }}</Label>
                <textarea
                    id="company-note-fact"
                    v-model="form.fact"
                    rows="2"
                    maxlength="500"
                    required
                    :placeholder="trans('company_notes.fact_placeholder')"
                    class="w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="form.errors.fact" />
            </div>
            <div class="grid gap-1">
                <Label for="company-note-source" class="text-xs">{{
                    trans('company_notes.source')
                }}</Label>
                <input
                    id="company-note-source"
                    v-model="form.source_url"
                    type="url"
                    :placeholder="trans('company_notes.source_placeholder')"
                    class="h-8 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="form.errors.source_url" />
            </div>
            <Button
                type="submit"
                size="sm"
                variant="secondary"
                class="justify-self-start"
                :disabled="form.processing"
                >{{ trans('company_notes.add') }}</Button
            >
        </form>
    </section>
</template>
