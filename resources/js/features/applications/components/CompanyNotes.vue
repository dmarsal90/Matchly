<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/company-notes';
import { router, useForm } from '@inertiajs/vue3';
import { ExternalLink, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import type { CompanyNoteEntry } from '../types';

const props = defineProps<{ company: string; notes: CompanyNoteEntry[] }>();

const form = useForm({ company: props.company, fact: '', source_url: '' });

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

        <ul v-if="notes.length" class="space-y-2">
            <li
                v-for="note in notes"
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
                        <Badge v-if="!note.approved" variant="secondary">{{
                            trans('company_notes.pending')
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
        <p v-else class="text-sm text-muted-foreground">
            {{ trans('company_notes.empty') }}
        </p>

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
