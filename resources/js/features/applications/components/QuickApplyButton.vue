<script setup lang="ts">
import { Button } from '@/components/ui/button';
import ApplyDialog from '@/features/applications/components/ApplyDialog.vue';
import type { QuickApplyOptions } from '@/features/applications/types';
import { login } from '@/routes';
import { Link } from '@inertiajs/vue3';
import { Zap } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

defineProps<{
    job: { id: number; company: string };
    /** null for guests. */
    options: QuickApplyOptions | null;
    size?: 'sm' | 'default';
}>();

const open = ref(false);
</script>

<template>
    <Button
        v-if="options"
        type="button"
        variant="outline"
        :size="size ?? 'sm'"
        class="relative z-10"
        :title="trans('quick_apply.button_hint')"
        @click="open = true"
    >
        <Zap class="size-4" aria-hidden="true" />
        {{ trans('quick_apply.button') }}
    </Button>
    <Button
        v-else
        variant="outline"
        :size="size ?? 'sm'"
        class="relative z-10"
        as-child
    >
        <Link :href="login()" :title="trans('quick_apply.sign_in')">
            <Zap class="size-4" aria-hidden="true" />
            {{ trans('quick_apply.button') }}
        </Link>
    </Button>
    <ApplyDialog
        v-if="options"
        v-model:open="open"
        :job="job"
        :options="options"
    />
</template>
