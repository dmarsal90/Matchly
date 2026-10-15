<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { redirect } from '@/routes/oauth';
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const page = usePage();
const providers = computed(() => page.props.oauthProviders);
const names = { google: 'Google', github: 'GitHub' } as const;
</script>

<template>
    <div v-if="providers.length > 0" class="space-y-4">
        <div class="grid gap-2">
            <!-- Full page navigation: the provider redirect cannot run inside an Inertia XHR. -->
            <Button
                v-for="provider in providers"
                :key="provider"
                as="a"
                variant="outline"
                class="w-full"
                :href="redirect.url(provider)"
            >
                {{
                    trans('oauth.continue_with', { provider: names[provider] })
                }}
            </Button>
        </div>
        <div
            class="flex items-center gap-3 text-xs text-muted-foreground uppercase"
        >
            <span class="h-px flex-1 bg-border" />
            {{ trans('oauth.or') }}
            <span class="h-px flex-1 bg-border" />
        </div>
    </div>
</template>
