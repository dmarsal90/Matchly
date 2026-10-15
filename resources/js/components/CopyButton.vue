<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ClipboardCheck, Copy } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

const props = defineProps<{ text: string; label?: string }>();

const copied = ref(false);

async function copy(): Promise<void> {
    await navigator.clipboard.writeText(props.text);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="sm"
        :aria-label="
            label ? `${trans('quick_apply.copy')}: ${label}` : undefined
        "
        @click="copy"
    >
        <ClipboardCheck v-if="copied" class="size-4" aria-hidden="true" />
        <Copy v-else class="size-4" aria-hidden="true" />
        <span aria-live="polite">{{
            copied ? trans('quick_apply.copied') : trans('quick_apply.copy')
        }}</span>
    </Button>
</template>
