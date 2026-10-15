<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { QuickApplyOptions } from '@/features/applications/types';
import { show as showPackage } from '@/routes/applications/packages';
import { quickApply } from '@/routes/jobs';
import { Link, useForm } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    job: { id: number; company: string };
    options: QuickApplyOptions;
}>();

const open = defineModel<boolean>('open', { required: true });

const TONES = ['formal', 'friendly', 'enthusiastic'] as const;
const LENGTHS = ['short', 'medium', 'long'] as const;

const form = useForm({
    tone: 'formal' as (typeof TONES)[number],
    length: 'medium' as (typeof LENGTHS)[number],
    use_ai: props.options.ai_available && props.options.ai_consented,
    ai_consent: false,
});

const needsConsent = computed(() => form.use_ai && !props.options.ai_consented);

function submit(): void {
    form.post(quickApply.url(props.job.id), {
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        trans('quick_apply.dialog.title')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        trans('quick_apply.dialog.description', {
                            company: job.company,
                        })
                    }}</DialogDescription>
                </DialogHeader>

                <p
                    v-if="options.packages && options.latest_package_id"
                    class="rounded-lg border bg-muted/40 p-3 text-sm"
                >
                    {{
                        transChoice(
                            'quick_apply.dialog.previous',
                            options.packages,
                        )
                    }}
                    <Link
                        :href="showPackage(options.latest_package_id)"
                        class="font-medium underline underline-offset-2"
                        >{{ trans('quick_apply.dialog.open_latest') }}</Link
                    >
                </p>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">
                        {{ trans('quick_apply.dialog.tone') }}
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="tone in TONES"
                            :key="tone"
                            class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                        >
                            <input
                                v-model="form.tone"
                                type="radio"
                                name="tone"
                                :value="tone"
                                class="accent-primary"
                            />
                            {{ trans(`quick_apply.tones.${tone}`) }}
                        </label>
                    </div>
                    <InputError :message="form.errors.tone" />
                </fieldset>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">
                        {{ trans('quick_apply.dialog.length') }}
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="length in LENGTHS"
                            :key="length"
                            class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                        >
                            <input
                                v-model="form.length"
                                type="radio"
                                name="length"
                                :value="length"
                                class="accent-primary"
                            />
                            {{ trans(`quick_apply.lengths.${length}`) }}
                        </label>
                    </div>
                </fieldset>

                <div class="grid gap-2">
                    <div
                        v-if="options.ai_available"
                        class="flex items-start gap-2"
                    >
                        <Checkbox
                            id="quick-apply-ai"
                            v-model="form.use_ai"
                            class="mt-0.5"
                        />
                        <Label
                            for="quick-apply-ai"
                            class="flex items-center gap-1.5 font-normal"
                        >
                            <Sparkles class="size-3.5" aria-hidden="true" />
                            {{ trans('quick_apply.dialog.use_ai') }}
                        </Label>
                    </div>
                    <div v-if="needsConsent" class="flex items-start gap-2">
                        <Checkbox
                            id="quick-apply-ai-consent"
                            v-model="form.ai_consent"
                            class="mt-0.5"
                        />
                        <Label
                            for="quick-apply-ai-consent"
                            class="text-xs leading-snug font-normal text-muted-foreground"
                            >{{ trans('quick_apply.dialog.ai_consent') }}</Label
                        >
                    </div>
                    <InputError :message="form.errors.ai_consent" />
                    <p
                        v-if="!form.use_ai"
                        class="text-xs text-muted-foreground"
                    >
                        {{ trans('quick_apply.dialog.no_ai_hint') }}
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="open = false"
                        >{{ trans('quick_apply.dialog.cancel') }}</Button
                    >
                    <Button type="submit" :disabled="form.processing">{{
                        trans('quick_apply.dialog.submit')
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
