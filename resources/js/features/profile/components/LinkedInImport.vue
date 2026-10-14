<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import LinkedInExportGuide from '@/features/profile/components/LinkedInExportGuide.vue';
import { linkedin as importLinkedin } from '@/routes/skills/imports';
import { useForm } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{
    maxKilobytes: number;
    exportUrl: string;
    consented: boolean;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm<{ file: File | null; consent: boolean }>({
    file: null,
    consent: props.consented,
});
const maxMegabytes = computed(() => Math.round(props.maxKilobytes / 1024));

// Server-side import errors (e.g. one already running) come back under "import".
const importError = computed(
    () => (form.errors as Record<string, string | undefined>).import,
);

function pick(event: Event): void {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit(): void {
    form.post(importLinkedin.url(), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            form.reset('file');
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
    <div class="grid gap-4">
        <form class="grid gap-3" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="linkedin-file">{{
                    trans('skills.linkedin.file')
                }}</Label>
                <input
                    id="linkedin-file"
                    ref="fileInput"
                    type="file"
                    accept=".zip,application/zip"
                    aria-describedby="linkedin-file-hint"
                    class="block w-full text-sm file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-accent"
                    required
                    @change="pick"
                />
                <p
                    id="linkedin-file-hint"
                    class="text-xs text-muted-foreground"
                >
                    {{
                        trans('skills.linkedin.max_size', {
                            size: String(maxMegabytes),
                        })
                    }}
                </p>
                <InputError :message="form.errors.file ?? importError" />
            </div>

            <div class="flex items-start gap-2">
                <Checkbox
                    id="linkedin-consent"
                    v-model="form.consent"
                    aria-describedby="linkedin-consent-hint"
                    class="mt-0.5"
                />
                <div class="grid gap-1">
                    <Label
                        for="linkedin-consent"
                        class="leading-snug font-normal"
                        >{{ trans('skills.linkedin.consent') }}</Label
                    >
                    <p
                        id="linkedin-consent-hint"
                        class="text-xs text-muted-foreground"
                    >
                        {{ trans('skills.linkedin.consent_hint') }}
                    </p>
                    <InputError :message="form.errors.consent" />
                </div>
            </div>

            <div>
                <Button type="submit" :disabled="form.processing || !form.file">
                    <Upload class="size-4" aria-hidden="true" />
                    {{ trans('skills.linkedin.import') }}
                </Button>
            </div>
        </form>

        <LinkedInExportGuide :export-url="exportUrl" />
    </div>
</template>
