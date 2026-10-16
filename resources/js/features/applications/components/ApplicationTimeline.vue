<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { store } from '@/routes/applications/events';
import { router } from '@inertiajs/vue3';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import type { ApplicationEventEntry } from '../types';

const props = defineProps<{
    applicationId: number;
    events: ApplicationEventEntry[];
    followUpDraft: string | null;
    thankYouDraft?: string | null;
}>();

const type = ref<'interview' | 'note' | 'follow_up'>('interview');
const date = ref(new Date().toISOString().slice(0, 10));
const note = ref('');
const saving = ref(false);

const formatDate = (iso: string): string =>
    new Intl.DateTimeFormat(getActiveLanguage(), {
        dateStyle: 'medium',
    }).format(new Date(iso));

function label(event: ApplicationEventEntry): string {
    switch (event.type) {
        case 'status_changed':
            return trans('applications.timeline.status_changed', {
                status: trans(`applications.status.${event.status}`),
            });
        case 'interview':
            return trans('applications.timeline.interview', {
                round: String(event.round ?? 1),
            });
        case 'follow_up':
            return trans('applications.timeline.follow_up');
        default:
            return trans('applications.timeline.note');
    }
}

function add(): void {
    router.post(
        store.url(props.applicationId),
        {
            type: type.value,
            occurred_at: date.value,
            note: note.value || null,
        },
        {
            preserveScroll: true,
            only: ['applications'],
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onSuccess: () => (note.value = ''),
        },
    );
}

async function copyDraft(text: string | null | undefined): Promise<void> {
    if (!text) {
        return;
    }
    try {
        await navigator.clipboard.writeText(text);
        toast.success(trans('applications.timeline.copied'));
    } catch {
        // Clipboard blocked: the text stays visible for manual copy.
    }
}
</script>

<template>
    <details>
        <summary class="cursor-pointer text-xs text-muted-foreground">
            {{ trans('applications.timeline.title') }}
            <span>({{ events.length }})</span>
        </summary>

        <div class="mt-2 space-y-3">
            <ol v-if="events.length" class="space-y-2 border-l pl-3 text-sm">
                <li v-for="event in events" :key="event.id">
                    <p class="font-medium">{{ label(event) }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDate(event.occurred_at) }}
                    </p>
                    <p
                        v-if="event.note"
                        class="text-xs whitespace-pre-line text-muted-foreground"
                    >
                        {{ event.note }}
                    </p>
                </li>
            </ol>
            <p v-else class="text-xs text-muted-foreground">
                {{ trans('applications.timeline.empty') }}
            </p>

            <div v-if="followUpDraft" class="space-y-1">
                <p class="text-xs font-medium">
                    {{ trans('applications.timeline.draft') }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ trans('applications.timeline.draft_hint') }}
                </p>
                <pre
                    class="max-h-40 overflow-auto rounded-md border bg-muted/40 p-2 text-xs whitespace-pre-wrap"
                    >{{ followUpDraft }}</pre>
                <Button
                    size="sm"
                    variant="outline"
                    @click="copyDraft(followUpDraft)"
                    >{{ trans('applications.timeline.copy') }}</Button
                >
            </div>

            <div v-if="thankYouDraft" class="space-y-1">
                <p class="text-xs font-medium">
                    {{ trans('applications.timeline.thanks') }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ trans('applications.timeline.thanks_hint') }}
                </p>
                <pre
                    class="max-h-40 overflow-auto rounded-md border bg-muted/40 p-2 text-xs whitespace-pre-wrap"
                    >{{ thankYouDraft }}</pre>
                <Button
                    size="sm"
                    variant="outline"
                    @click="copyDraft(thankYouDraft)"
                    >{{ trans('applications.timeline.copy') }}</Button
                >
            </div>

            <form class="grid gap-2" @submit.prevent="add">
                <div class="grid gap-1">
                    <Label
                        :for="`event-type-${applicationId}`"
                        class="text-xs"
                        >{{ trans('applications.timeline.type') }}</Label
                    >
                    <NativeSelect
                        :id="`event-type-${applicationId}`"
                        v-model="type"
                        class="h-8 text-sm"
                    >
                        <option value="interview">
                            {{ trans('applications.status.interview') }}
                        </option>
                        <option value="follow_up">
                            {{ trans('applications.timeline.follow_up') }}
                        </option>
                        <option value="note">
                            {{ trans('applications.timeline.note') }}
                        </option>
                    </NativeSelect>
                </div>
                <div class="grid gap-1">
                    <Label
                        :for="`event-date-${applicationId}`"
                        class="text-xs"
                        >{{ trans('applications.timeline.date') }}</Label
                    >
                    <input
                        :id="`event-date-${applicationId}`"
                        v-model="date"
                        type="date"
                        required
                        class="h-8 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                </div>
                <textarea
                    v-model="note"
                    rows="2"
                    maxlength="5000"
                    :aria-label="trans('applications.timeline.note_label')"
                    :placeholder="
                        trans('applications.timeline.note_placeholder')
                    "
                    class="w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <Button
                    type="submit"
                    size="sm"
                    variant="secondary"
                    :disabled="saving"
                    >{{ trans('applications.timeline.save') }}</Button
                >
            </form>
        </div>
    </details>
</template>
