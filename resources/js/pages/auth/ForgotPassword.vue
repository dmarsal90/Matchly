<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'account.auth.forgot.title',
        description: 'account.auth.forgot.description',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="trans('account.auth.forgot.title')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-slot="{ errors, processing }" v-bind="email.form()">
            <div class="grid gap-2">
                <Label for="email">{{ trans('account.auth.email') }}</Label>
                <Input
                    id="email"
                    v-focus
                    type="email"
                    name="email"
                    autocomplete="off"
                    :placeholder="trans('account.auth.email_placeholder')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ trans('account.auth.forgot.submit') }}
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>{{ trans('account.auth.forgot.return_to') }}</span>
            <TextLink :href="login()">{{
                trans('account.auth.forgot.log_in')
            }}</TextLink>
        </div>
    </div>
</template>
