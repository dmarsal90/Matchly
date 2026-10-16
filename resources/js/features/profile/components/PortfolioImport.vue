<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PortfolioState } from '@/features/profile/types';
import { portfolio as importPortfolio } from '@/routes/skills/imports';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    state: PortfolioState;
    busy: boolean;
    consented: boolean;
}>();

const form = useForm({
    url: props.state.suggested_url ?? '',
    consent: props.consented,
});

// Server-side import errors (e.g. one already running) come back under "import".
const importError = computed(
    () => (form.errors as Record<string, string | undefined>).import,
);

function submit(): void {
    form.post(importPortfolio.url(), { preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-3" @submit.prevent="submit">
        <div class="grid gap-1.5">
            <Label for="portfolio-url">{{
                trans('skills.portfolio.url')
            }}</Label>
            <Input
                id="portfolio-url"
                v-model="form.url"
                type="url"
                inputmode="url"
                autocomplete="url"
                spellcheck="false"
                maxlength="255"
                :placeholder="trans('skills.portfolio.url_placeholder')"
                required
            />
            <InputError :message="form.errors.url ?? importError" />
        </div>

        <div class="flex items-start gap-2">
            <Checkbox
                id="portfolio-consent"
                v-model="form.consent"
                aria-describedby="portfolio-consent-hint"
                class="mt-0.5"
            />
            <div class="grid gap-1">
                <Label
                    for="portfolio-consent"
                    class="leading-snug font-normal"
                    >{{ trans('skills.portfolio.consent') }}</Label
                >
                <p
                    id="portfolio-consent-hint"
                    class="text-xs text-muted-foreground"
                >
                    {{ trans('skills.portfolio.consent_hint') }}
                </p>
                <InputError :message="form.errors.consent" />
            </div>
        </div>

        <div>
            <Button type="submit" :disabled="form.processing || busy">
                {{ trans('skills.portfolio.import') }}
            </Button>
        </div>
    </form>
</template>
