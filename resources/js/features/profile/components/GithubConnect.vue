<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { GithubState } from '@/features/profile/types';
import { redirect } from '@/routes/oauth';
import { github as importGithub } from '@/routes/skills/imports';
import { useForm } from '@inertiajs/vue3';
import { Link2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    state: GithubState;
    busy: boolean;
    consented: boolean;
}>();

const form = useForm({
    username: props.state.suggested_username ?? '',
    consent: props.consented,
});

// Server-side import errors (e.g. one already running) come back under "import".
const importError = computed(
    () => (form.errors as Record<string, string | undefined>).import,
);

function submit(): void {
    form.post(importGithub.url(), { preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-3" @submit.prevent="submit">
        <div class="grid gap-1.5">
            <Label for="github-username">{{
                trans('skills.github.username')
            }}</Label>
            <Input
                id="github-username"
                v-model="form.username"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                maxlength="255"
                :placeholder="trans('skills.github.username_placeholder')"
                required
            />
            <InputError :message="form.errors.username ?? importError" />
        </div>

        <div class="flex items-start gap-2">
            <Checkbox
                id="github-consent"
                v-model="form.consent"
                aria-describedby="github-consent-hint"
                class="mt-0.5"
            />
            <div class="grid gap-1">
                <Label for="github-consent" class="leading-snug font-normal">{{
                    trans('skills.github.consent')
                }}</Label>
                <p
                    id="github-consent-hint"
                    class="text-xs text-muted-foreground"
                >
                    {{ trans('skills.github.consent_hint') }}
                </p>
                <InputError :message="form.errors.consent" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button type="submit" :disabled="form.processing || busy">
                {{ trans('skills.github.import') }}
            </Button>
            <!-- Full page navigation: the OAuth redirect cannot run inside an Inertia request. -->
            <Button
                v-if="state.oauth_available && !state.linked_username"
                variant="outline"
                as-child
            >
                <a
                    :href="
                        redirect.url('github', { query: { return: 'skills' } })
                    "
                >
                    <Link2 class="size-4" aria-hidden="true" />
                    {{ trans('skills.github.connect') }}
                </a>
            </Button>
        </div>
        <p v-if="state.linked_username" class="text-xs text-muted-foreground">
            {{
                trans('skills.github.connected', {
                    username: state.linked_username,
                })
            }}
        </p>
        <p
            v-else-if="state.oauth_available"
            class="text-xs text-muted-foreground"
        >
            {{ trans('skills.github.connect_hint') }}
        </p>
    </form>
</template>
