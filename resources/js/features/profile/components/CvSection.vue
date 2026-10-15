<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { CvDocument } from '@/features/profile/types';
import { destroy, download, primary, store } from '@/routes/career/cv';
import { router, useForm, usePoll } from '@inertiajs/vue3';
import { Download, FileText, Star, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    documents: CvDocument[];
    maxKilobytes: number;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm<{ file: File | null; consent: boolean }>({
    file: null,
    consent: false,
});

const maxMegabytes = computed(() => Math.round(props.maxKilobytes / 1024));

function pick(event: Event): void {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function upload(): void {
    form.post(store.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('file');
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function remove(document: CvDocument): void {
    if (window.confirm(trans('profile.cv.delete_confirm'))) {
        router.delete(destroy.url(document.id), { preserveScroll: true });
    }
}

function makePrimary(document: CvDocument): void {
    router.patch(primary.url(document.id), {}, { preserveScroll: true });
}

// Text extraction runs in the queue: refresh only this prop until it settles.
const { start, stop } = usePoll(
    3000,
    { only: ['cvDocuments'] },
    { autoStart: false },
);
const processing = computed(() =>
    props.documents.some((document) => document.status === 'uploaded'),
);
watch(processing, (value) => (value ? start() : stop()), { immediate: true });

const formatSize = (bytes: number): string =>
    bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
</script>

<template>
    <section class="space-y-6">
        <Heading
            variant="small"
            :title="trans('profile.cv.title')"
            :description="
                trans('profile.cv.description', { size: String(maxMegabytes) })
            "
        />

        <ul v-if="documents.length > 0" class="divide-y rounded-lg border">
            <li
                v-for="document in documents"
                :key="document.id"
                class="flex flex-wrap items-center gap-3 p-3"
            >
                <FileText
                    class="size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ document.name }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ document.type.toUpperCase() }} ·
                        {{ formatSize(document.size) }} ·
                        <span
                            :class="
                                document.status === 'failed' &&
                                'text-destructive'
                            "
                            >{{
                                trans(`profile.cv.status.${document.status}`)
                            }}</span
                        >
                    </p>
                </div>
                <Badge v-if="document.is_primary" variant="secondary">{{
                    trans('profile.cv.primary')
                }}</Badge>
                <div class="flex items-center gap-1">
                    <Button
                        v-if="!document.is_primary"
                        variant="ghost"
                        size="icon"
                        :aria-label="trans('profile.cv.make_primary')"
                        :title="trans('profile.cv.make_primary')"
                        @click="makePrimary(document)"
                    >
                        <Star class="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        as="a"
                        variant="ghost"
                        size="icon"
                        :href="download.url(document.id)"
                        :aria-label="trans('profile.cv.download')"
                        :title="trans('profile.cv.download')"
                    >
                        <Download class="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        :aria-label="trans('profile.cv.delete')"
                        :title="trans('profile.cv.delete')"
                        @click="remove(document)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </Button>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
            {{ trans('profile.cv.empty') }}
        </p>

        <form
            class="space-y-4 rounded-lg border border-dashed p-4"
            @submit.prevent="upload"
        >
            <div class="grid gap-2">
                <Label for="cv-file">{{ trans('profile.cv.choose') }}</Label>
                <input
                    id="cv-file"
                    ref="fileInput"
                    type="file"
                    accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-secondary-foreground"
                    @change="pick"
                />
                <InputError :message="form.errors.file" />
            </div>

            <div class="flex items-start gap-2">
                <Checkbox
                    id="cv-consent"
                    v-model="form.consent"
                    class="mt-0.5"
                />
                <div class="grid gap-1">
                    <Label for="cv-consent" class="leading-snug font-normal">{{
                        trans('profile.cv.consent_label')
                    }}</Label>
                    <p class="text-xs text-muted-foreground">
                        {{ trans('profile.cv.consent_hint') }}
                    </p>
                    <InputError :message="form.errors.consent" />
                </div>
            </div>

            <Button
                type="submit"
                :disabled="form.processing || !form.file || !form.consent"
            >
                {{ trans('profile.cv.upload') }}
            </Button>
        </form>
    </section>
</template>
