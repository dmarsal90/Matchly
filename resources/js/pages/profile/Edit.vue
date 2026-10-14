<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import CheckboxGroup from '@/components/CheckboxGroup.vue';
import CvSection from '@/features/profile/components/CvSection.vue';
import LanguagesField from '@/features/profile/components/LanguagesField.vue';
import type {
    CareerProfile,
    CvDocument,
    ProfileOptions,
} from '@/features/profile/types';
import { edit, update } from '@/routes/career';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    profile: CareerProfile;
    cvDocuments: CvDocument[];
    options: ProfileOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Career profile', href: edit() }],
    },
});

const form = useForm<CareerProfile>({
    ...props.profile,
    languages: props.profile.languages.map((language) => ({ ...language })),
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const workModeOptions = computed(() =>
    props.options.workModes.map((value) => ({
        value,
        label: trans(`profile.work_modes.${value}`),
    })),
);
const employmentOptions = computed(() =>
    props.options.employmentTypes.map((value) => ({
        value,
        label: trans(`profile.employment_types.${value}`),
    })),
);

function submit(): void {
    form.put(update.url(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="trans('profile.title')" />

    <div class="mx-auto w-full max-w-3xl space-y-10 px-4 py-6">
        <Heading
            :title="trans('profile.title')"
            :description="trans('profile.description')"
        />

        <p
            class="rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground"
        >
            {{ trans('profile.fairness') }}
        </p>

        <form class="space-y-10" @submit.prevent="submit">
            <section class="space-y-6">
                <Heading
                    variant="small"
                    :title="trans('profile.sections.about')"
                />

                <div class="grid gap-2">
                    <Label for="headline">{{
                        trans('profile.fields.headline')
                    }}</Label>
                    <Input
                        id="headline"
                        v-model="form.headline"
                        maxlength="120"
                        :placeholder="
                            trans('profile.fields.headline_placeholder')
                        "
                    />
                    <InputError :message="form.errors.headline" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="country">{{
                            trans('profile.fields.country')
                        }}</Label>
                        <NativeSelect
                            id="country"
                            v-model="form.country"
                            aria-describedby="country-hint"
                        >
                            <option :value="null">
                                {{ trans('profile.fields.country_none') }}
                            </option>
                            <option
                                v-for="(name, code) in options.countries"
                                :key="code"
                                :value="code"
                            >
                                {{ name }}
                            </option>
                        </NativeSelect>
                        <p
                            id="country-hint"
                            class="text-xs text-muted-foreground"
                        >
                            {{ trans('profile.fields.country_hint') }}
                        </p>
                        <InputError :message="form.errors.country" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="city">{{
                            trans('profile.fields.city')
                        }}</Label>
                        <Input
                            id="city"
                            v-model="form.city"
                            maxlength="80"
                            autocomplete="address-level2"
                        />
                        <InputError :message="form.errors.city" />
                    </div>
                </div>

                <div class="grid gap-2 sm:max-w-xs">
                    <Label for="phone">{{
                        trans('profile.fields.phone')
                    }}</Label>
                    <Input
                        id="phone"
                        v-model="form.phone"
                        type="tel"
                        autocomplete="tel"
                        maxlength="30"
                    />
                    <InputError :message="form.errors.phone" />
                </div>
            </section>

            <Separator />

            <section class="space-y-6">
                <Heading
                    variant="small"
                    :title="trans('profile.sections.links')"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="github_username">{{
                            trans('profile.fields.github_username')
                        }}</Label>
                        <Input
                            id="github_username"
                            v-model="form.github_username"
                            autocapitalize="off"
                            spellcheck="false"
                        />
                        <InputError :message="form.errors.github_username" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="linkedin_url">{{
                            trans('profile.fields.linkedin_url')
                        }}</Label>
                        <Input
                            id="linkedin_url"
                            v-model="form.linkedin_url"
                            type="url"
                            placeholder="https://www.linkedin.com/in/…"
                        />
                        <InputError :message="form.errors.linkedin_url" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="portfolio_url">{{
                            trans('profile.fields.portfolio_url')
                        }}</Label>
                        <Input
                            id="portfolio_url"
                            v-model="form.portfolio_url"
                            type="url"
                            placeholder="https://…"
                        />
                        <InputError :message="form.errors.portfolio_url" />
                    </div>
                </div>
            </section>

            <Separator />

            <section class="space-y-6">
                <Heading
                    variant="small"
                    :title="trans('profile.sections.languages')"
                />
                <LanguagesField
                    v-model="form.languages"
                    :levels="options.languageLevels"
                    :errors="errors"
                />
            </section>

            <Separator />

            <section class="space-y-6">
                <Heading
                    variant="small"
                    :title="trans('profile.sections.salary')"
                />
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="salary_min">{{
                            trans('profile.fields.salary_min')
                        }}</Label>
                        <Input
                            id="salary_min"
                            v-model.number="form.salary_min"
                            type="number"
                            min="0"
                            step="1000"
                            inputmode="numeric"
                        />
                        <InputError :message="form.errors.salary_min" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="salary_currency">{{
                            trans('profile.fields.salary_currency')
                        }}</Label>
                        <NativeSelect
                            id="salary_currency"
                            v-model="form.salary_currency"
                        >
                            <option :value="null">—</option>
                            <option
                                v-for="currency in options.currencies"
                                :key="currency"
                                :value="currency"
                            >
                                {{ currency }}
                            </option>
                        </NativeSelect>
                        <InputError :message="form.errors.salary_currency" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="salary_period">{{
                            trans('profile.fields.salary_period')
                        }}</Label>
                        <NativeSelect
                            id="salary_period"
                            v-model="form.salary_period"
                        >
                            <option :value="null">—</option>
                            <option
                                v-for="period in options.salaryPeriods"
                                :key="period"
                                :value="period"
                            >
                                {{ trans(`profile.periods.${period}`) }}
                            </option>
                        </NativeSelect>
                        <InputError :message="form.errors.salary_period" />
                    </div>
                </div>
            </section>

            <Separator />

            <section class="space-y-6">
                <Heading
                    variant="small"
                    :title="trans('profile.sections.preferences')"
                />
                <CheckboxGroup
                    v-model="form.work_modes"
                    name="work_modes"
                    :legend="trans('profile.fields.work_modes')"
                    :options="workModeOptions"
                />
                <CheckboxGroup
                    v-model="form.employment_types"
                    name="employment_types"
                    :legend="trans('profile.fields.employment_types')"
                    :options="employmentOptions"
                />
            </section>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="form.processing">{{
                    trans('profile.save')
                }}</Button>
                <p
                    v-if="form.recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    {{ trans('profile.saved') }}
                </p>
            </div>
        </form>

        <Separator />

        <CvSection
            :documents="cvDocuments"
            :max-kilobytes="options.maxCvKilobytes"
        />
    </div>
</template>
