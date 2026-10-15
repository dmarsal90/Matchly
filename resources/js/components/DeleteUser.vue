<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Modules/Identity/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuthUser } from '@/composables/useAuthUser';

const passwordInput = useTemplateRef('passwordInput');
const user = useAuthUser();
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            :title="trans('account.delete.title')"
            :description="trans('account.delete.description')"
        />
        <div
            class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
        >
            <div class="relative space-y-0.5 text-red-700 dark:text-red-100">
                <p class="font-medium">{{ trans('account.delete.warning') }}</p>
                <p class="text-sm">
                    {{ trans('account.delete.caution') }}
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        data-test="delete-user-button"
                        >{{ trans('account.delete.button') }}</Button
                    >
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-slot="{ errors, processing, reset, clearErrors }"
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        @error="() => passwordInput?.focus()"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>{{
                                trans('account.delete.confirm_title')
                            }}</DialogTitle>
                            <DialogDescription>
                                {{
                                    trans('account.delete.confirm_description')
                                }}
                            </DialogDescription>
                        </DialogHeader>

                        <div v-if="!user.has_password" class="grid gap-2">
                            <!-- OAuth-only accounts have no password: they confirm by typing their e-mail. -->
                            <Label for="email_confirmation">{{
                                user.email
                            }}</Label>
                            <Input
                                id="email_confirmation"
                                name="email_confirmation"
                                type="email"
                                autocomplete="off"
                                :placeholder="user.email"
                            />
                            <InputError :message="errors.email_confirmation" />
                        </div>

                        <div v-else class="grid gap-2">
                            <Label for="password" class="sr-only">{{
                                trans('account.auth.password')
                            }}</Label>
                            <PasswordInput
                                id="password"
                                ref="passwordInput"
                                name="password"
                                :placeholder="trans('account.auth.password')"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    {{ trans('common.cancel') }}
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                {{ trans('account.delete.button') }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
