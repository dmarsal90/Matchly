<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ContentSource } from '@/features/applications/types';
import { Download } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    source: ContentSource | null;
    downloadUrl: string;
    readonly?: boolean;
}>();

const letter = defineModel<string>({ required: true });
</script>

<template>
    <div class="grid gap-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Badge v-if="source" variant="outline">{{
                trans(`quick_apply.package.source.${source}`)
            }}</Badge>
            <div class="flex flex-wrap gap-1">
                <CopyButton
                    :text="letter"
                    :label="trans('quick_apply.sections.letter')"
                />
                <Button variant="ghost" size="sm" as-child>
                    <a :href="downloadUrl"
                        ><Download class="size-4" aria-hidden="true" />{{
                            trans('quick_apply.download_letter')
                        }}</a
                    >
                </Button>
            </div>
        </div>
        <label for="cover-letter" class="sr-only">{{
            trans('quick_apply.sections.letter')
        }}</label>
        <textarea
            id="cover-letter"
            v-model="letter"
            :readonly="readonly"
            rows="16"
            maxlength="8000"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-serif text-sm leading-relaxed shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
        />
    </div>
</template>
