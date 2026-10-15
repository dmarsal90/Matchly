<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'account.auth.verify.title',
        description: 'account.auth.verify.description',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="trans('account.auth.verify.title')" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ trans('account.auth.verify.sent') }}
    </div>

    <Form
        v-slot="{ processing }"
        v-bind="send.form()"
        class="space-y-6 text-center"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ trans('account.auth.verify.resend') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ trans('account.auth.log_out') }}
        </TextLink>
    </Form>
</template>
