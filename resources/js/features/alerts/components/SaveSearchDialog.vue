<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { filterChips } from '@/features/alerts/lib/summary';
import { store } from '@/routes/alerts';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';

const props = defineProps<{
    /** Current listing query (from toQuery). */
    filters: Record<string, string>;
    emailConsented: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const FREQUENCIES = ['immediate', 'daily', 'weekly'] as const;

const form = useForm({
    name: '',
    filters: {} as Record<string, string>,
    frequency: 'daily' as (typeof FREQUENCIES)[number],
    email: true,
    email_consent: false,
});

watch(open, (value) => {
    if (value) {
        form.reset();
        form.clearErrors();
        form.filters = { ...props.filters };
        form.name = props.filters.q ?? '';
    }
});

const chips = computed(() => filterChips(form.filters));

function submit(): void {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ trans('alerts.form.title') }}</DialogTitle>
                    <DialogDescription>{{
                        trans('alerts.save_search_hint')
                    }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <p class="text-sm font-medium">
                        {{ trans('alerts.form.filters') }}
                    </p>
                    <ul v-if="chips.length" class="flex flex-wrap gap-1.5">
                        <li
                            v-for="chip in chips"
                            :key="chip"
                            class="rounded-full border px-2 py-0.5 text-xs"
                        >
                            {{ chip }}
                        </li>
                    </ul>
                    <p v-else class="text-xs text-muted-foreground">
                        {{ trans('alerts.form.no_filters') }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <Label for="alert-name">{{
                        trans('alerts.form.name')
                    }}</Label>
                    <Input
                        id="alert-name"
                        v-model="form.name"
                        maxlength="80"
                        required
                        :placeholder="trans('alerts.form.name_placeholder')"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="alert-frequency">{{
                        trans('alerts.form.frequency')
                    }}</Label>
                    <NativeSelect id="alert-frequency" v-model="form.frequency">
                        <option
                            v-for="value in FREQUENCIES"
                            :key="value"
                            :value="value"
                        >
                            {{ trans(`alerts.frequencies.${value}`) }}
                        </option>
                    </NativeSelect>
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center gap-2">
                        <Checkbox id="alert-email" v-model="form.email" />
                        <Label for="alert-email" class="font-normal">{{
                            trans('alerts.form.email')
                        }}</Label>
                    </div>
                    <div
                        v-if="form.email && !emailConsented"
                        class="flex items-start gap-2"
                    >
                        <Checkbox
                            id="alert-email-consent"
                            v-model="form.email_consent"
                            class="mt-0.5"
                        />
                        <Label
                            for="alert-email-consent"
                            class="text-xs leading-snug font-normal text-muted-foreground"
                            >{{ trans('alerts.form.email_consent') }}</Label
                        >
                    </div>
                    <InputError :message="form.errors.email_consent" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="open = false"
                        >{{ trans('alerts.form.cancel') }}</Button
                    >
                    <Button type="submit" :disabled="form.processing">{{
                        trans('alerts.form.save')
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
