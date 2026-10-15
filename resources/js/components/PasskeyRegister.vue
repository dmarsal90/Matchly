<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const emit = defineEmits<{
    success: [];
}>();

const getDefaultPasskeyName = () => {
    const ua = navigator.userAgent;

    const browser = [
        { pattern: /Edg|Edge/, name: 'Edge' },
        { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
        { pattern: /Firefox|FxiOS/, name: 'Firefox' },
        { pattern: /Chrome|CriOS/, name: 'Chrome' },
        { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    const os = [
        { pattern: /iPhone/, name: 'iPhone' },
        { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
        { pattern: /Android/, name: 'Android' },
        { pattern: /Mac/, name: 'Mac' },
        { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    if (browser && os)
        return trans('account.passkeys.default_name', { browser, os });

    return browser ?? os ?? '';
};

const name = ref(getDefaultPasskeyName());
const showForm = ref(false);

const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
        name.value = '';
        showForm.value = false;
        emit('success');
    },
});

const handleSubmit = async (event: Event) => {
    event.preventDefault();

    if (!name.value.trim()) {
        return;
    }

    await register(name.value);
};

const handleCancel = () => {
    showForm.value = false;
    name.value = '';
};
</script>

<template>
    <div v-if="!isSupported" class="text-sm text-muted-foreground">
        {{ trans('account.passkeys.unsupported') }}
    </div>

    <Button v-else-if="!showForm" variant="outline" @click="showForm = true">
        {{ trans('account.passkeys.add') }}
    </Button>

    <form
        v-else
        class="space-y-4 rounded-lg border border-border bg-muted/50 p-4"
        @submit="handleSubmit"
    >
        <div class="grid gap-2">
            <Label for="passkey-name">{{
                trans('account.passkeys.name')
            }}</Label>
            <Input
                id="passkey-name"
                v-model="name"
                v-focus
                type="text"
                :placeholder="trans('account.passkeys.name_placeholder')"
                class="mt-1 block w-full border-foreground/20"
            />
            <p class="text-xs text-muted-foreground">
                {{ trans('account.passkeys.name_hint') }}
            </p>
        </div>

        <InputError v-if="error" :message="error" />

        <div class="flex gap-2">
            <Button type="submit" :disabled="isLoading || !name.trim()">
                {{
                    isLoading
                        ? trans('account.passkeys.registering')
                        : trans('account.passkeys.register')
                }}
            </Button>
            <Button type="button" variant="ghost" @click="handleCancel">
                {{ trans('common.cancel') }}
            </Button>
        </div>
    </form>
</template>
