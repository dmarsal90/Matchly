<script setup lang="ts">
import { ExternalLink, Info, ShieldCheck } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

defineProps<{ exportUrl: string }>();

// Steps live in the translations (LinkedIn renames its menus): read as many as there are.
const steps = computed(() => {
    const items: string[] = [];
    for (let index = 0; index < 15; index++) {
        const key = `skills.linkedin_guide.steps.${index}`;
        const text = trans(key);
        if (text === key) break;
        items.push(text);
    }

    return items;
});
</script>

<template>
    <section
        class="rounded-lg border bg-muted/30 p-4 text-sm"
        aria-labelledby="linkedin-guide-title"
    >
        <h4 id="linkedin-guide-title" class="mb-3 font-medium">
            {{ trans('skills.linkedin_guide.title') }}
        </h4>
        <ol class="list-decimal space-y-1.5 pl-5 marker:text-muted-foreground">
            <li v-for="(step, index) in steps" :key="index">{{ step }}</li>
        </ol>
        <a
            :href="exportUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-3 inline-flex items-center gap-1 text-sm font-medium underline underline-offset-2"
        >
            {{ trans('skills.linkedin_guide.open_settings') }}
            <ExternalLink class="size-3.5" aria-hidden="true" />
        </a>
        <p class="mt-4 flex items-start gap-2 text-muted-foreground">
            <ShieldCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{ trans('skills.linkedin_guide.privacy') }}
        </p>
        <p class="mt-1 flex items-start gap-2 text-muted-foreground">
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{ trans('skills.linkedin_guide.menus_note') }}
        </p>
    </section>
</template>
