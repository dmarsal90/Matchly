<script setup lang="ts">
import { update } from '@/routes/locale';
import { router, usePage } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import { loadLanguageAsync, trans } from 'laravel-vue-i18n';

const page = usePage();

async function change(event: Event): Promise<void> {
    const locale = (event.target as HTMLSelectElement).value;

    await loadLanguageAsync(locale);
    router.put(update.url(), { locale }, { preserveScroll: true });
}
</script>

<template>
    <label
        class="relative inline-flex h-9 items-center gap-1.5 rounded-md px-2 text-sm text-muted-foreground focus-within:ring-2 focus-within:ring-ring hover:bg-accent hover:text-accent-foreground"
    >
        <Languages class="size-4" aria-hidden="true" />
        <span class="sr-only">{{ trans('app.language') }}</span>
        <select
            class="cursor-pointer appearance-none bg-transparent pr-1 uppercase focus:outline-none"
            :value="page.props.locale"
            @change="change"
        >
            <option
                v-for="locale in page.props.supportedLocales"
                :key="locale"
                :value="locale"
            >
                {{ trans(`locales.${locale}`) }}
            </option>
        </select>
    </label>
</template>
