<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { save } from '@/routes/jobs';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        jobId: number;
        trackingStatus: string | null;
        reload?: string[];
        withLabel?: boolean;
    }>(),
    {
        reload: () => ['jobs'],
        withLabel: false,
    },
);

const page = usePage();
const pending = ref(false);
const saved = computed(() => props.trackingStatus !== null);
const label = computed(() =>
    trans(saved.value ? 'jobs.card.unsave' : 'jobs.card.save'),
);

function toggle(): void {
    pending.value = true;
    router.post(
        save.url(props.jobId),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: props.reload,
            onFinish: () => (pending.value = false),
        },
    );
}
</script>

<template>
    <Button
        v-if="!page.props.auth.user"
        as-child
        variant="ghost"
        :size="withLabel ? 'sm' : 'icon'"
        :title="trans('jobs.card.sign_in_to_save')"
    >
        <Link :href="login()" :aria-label="trans('jobs.card.sign_in_to_save')">
            <Star class="size-4" aria-hidden="true" />
            <span v-if="withLabel">{{ trans('jobs.card.save') }}</span>
        </Link>
    </Button>
    <Button
        v-else
        variant="ghost"
        :size="withLabel ? 'sm' : 'icon'"
        :aria-pressed="saved"
        :aria-label="withLabel ? undefined : label"
        :title="label"
        :disabled="pending"
        @click="toggle"
    >
        <Star
            class="size-4"
            :class="saved && 'fill-amber-400 text-amber-500'"
            aria-hidden="true"
        />
        <span v-if="withLabel">{{
            trans(saved ? 'jobs.card.saved' : 'jobs.card.save')
        }}</span>
    </Button>
</template>
