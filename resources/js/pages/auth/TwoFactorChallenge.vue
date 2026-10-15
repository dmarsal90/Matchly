<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: trans('account.auth.challenge.recovery_title'),
            description: trans('account.auth.challenge.recovery_description'),
            buttonText: trans('account.auth.challenge.use_code'),
        };
    }

    return {
        title: trans('account.auth.challenge.code_title'),
        description: trans('account.auth.challenge.code_description'),
        buttonText: trans('account.auth.challenge.use_recovery'),
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head :title="trans('account.auth.challenge.head')" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-slot="{ errors, processing, clearErrors }"
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                @error="code = ''"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors.code" />
                </div>
                <Button type="submit" class="w-full" :disabled="processing">{{
                    trans('common.continue')
                }}</Button>
                <div class="text-center text-sm text-muted-foreground">
                    <span
                        >{{ trans('account.auth.challenge.or_you_can') }}
                    </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-slot="{ errors, processing, clearErrors }"
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
            >
                <Input
                    v-focus
                    name="recovery_code"
                    type="text"
                    :placeholder="
                        trans('account.auth.challenge.recovery_placeholder')
                    "
                    required
                />
                <InputError :message="errors.recovery_code" />
                <Button type="submit" class="w-full" :disabled="processing">{{
                    trans('common.continue')
                }}</Button>

                <div class="text-center text-sm text-muted-foreground">
                    <span
                        >{{ trans('account.auth.challenge.or_you_can') }}
                    </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
