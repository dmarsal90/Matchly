<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { useAuthUser } from '@/composables/useAuthUser';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import ProfileController from '@/actions/App/Modules/Identity/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'account.settings.profile.head',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = useAuthUser();
</script>

<template>
    <Head :title="trans('account.settings.profile.head')" />

    <h1 class="sr-only">{{ trans('account.settings.profile.head') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="trans('account.settings.profile.title')"
            :description="trans('account.settings.profile.description')"
        />

        <Form
            v-slot="{ errors, processing }"
            v-bind="ProfileController.update.form()"
            class="space-y-6"
        >
            <div class="grid gap-2">
                <Label for="name">{{ trans('account.auth.name') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="trans('account.auth.name_placeholder')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ trans('account.auth.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    :placeholder="trans('account.auth.email')"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    {{ trans('account.settings.profile.unverified') }}
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        {{ trans('account.settings.profile.resend') }}
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    {{ trans('account.settings.profile.sent') }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                    >{{ trans('common.save') }}</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
