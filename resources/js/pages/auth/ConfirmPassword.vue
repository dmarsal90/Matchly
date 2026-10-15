<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'account.auth.confirm.title',
        description: 'account.auth.confirm.description',
    },
});
</script>

<template>
    <Head :title="trans('account.auth.confirm.title')" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        :label="trans('account.auth.confirm.passkey')"
        :loading-label="trans('account.auth.confirm.passkey_loading')"
        :separator="trans('account.auth.confirm.or_password')"
    />

    <Form
        v-slot="{ errors, processing }"
        v-bind="store.form()"
        reset-on-success
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label html-for="password">{{
                    trans('account.auth.password')
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ trans('account.auth.confirm.submit') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
