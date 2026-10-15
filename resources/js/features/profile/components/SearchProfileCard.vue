<script setup lang="ts">
import CheckboxGroup from '@/components/CheckboxGroup.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import type { SearchProfile, SkillsOptions } from '@/features/profile/types';
import { index as jobsIndex } from '@/routes/jobs';
import { regenerate, update } from '@/routes/skills/search';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, RefreshCw, Search, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{
    profile: SearchProfile | null;
    options: SkillsOptions;
}>();

const editing = ref(false);
const newTechnology = ref('');

const form = useForm({
    roles: [] as string[],
    technologies: [] as string[],
    seniorities: [] as string[],
    work_modes: [] as string[],
    employment_types: [] as string[],
    salary_min: null as number | null,
    salary_currency: null as string | null,
});

const choices = (values: string[], prefix: string) =>
    values.map((value) => ({ value, label: trans(`${prefix}.${value}`) }));
const roleOptions = computed(() => choices(props.options.roles, 'jobs.role'));
const seniorityOptions = computed(() =>
    choices(props.options.seniorities, 'jobs.seniority'),
);
const workModeOptions = computed(() =>
    choices(props.options.workModes, 'jobs.work_mode'),
);
const typeOptions = computed(() =>
    choices(props.options.employmentTypes, 'jobs.type'),
);

function startEditing(): void {
    const profile = props.profile;
    form.defaults({
        roles: [...(profile?.roles ?? [])],
        // Edited as display names; the server normalises them back to skills.
        technologies: [...(profile?.technology_names ?? [])],
        seniorities: [...(profile?.seniorities ?? [])],
        work_modes: [...(profile?.work_modes ?? [])],
        employment_types: [...(profile?.employment_types ?? [])],
        salary_min: profile?.salary_min ?? null,
        salary_currency: profile?.salary_currency ?? null,
    });
    form.reset();
    form.clearErrors();
    editing.value = true;
}

function addTechnology(): void {
    const name = newTechnology.value.trim();
    if (
        name &&
        !form.technologies.some((t) => t.toLowerCase() === name.toLowerCase())
    ) {
        form.technologies = [...form.technologies, name];
    }
    newTechnology.value = '';
}

function save(): void {
    form.put(update.url(), {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}

function regenerateProfile(): void {
    if (
        !props.profile?.edited ||
        window.confirm(trans('skills.search_profile.regenerate_confirm'))
    ) {
        router.post(regenerate.url(), {}, { preserveScroll: true });
    }
}

const summary = computed(() => {
    const profile = props.profile;
    if (!profile) return [];
    const labels = (values: string[], prefix: string) =>
        values.map((value) => trans(`${prefix}.${value}`)).join(', ');

    return [
        {
            label: trans('skills.search_profile.roles'),
            value: labels(profile.roles, 'jobs.role'),
        },
        {
            label: trans('skills.search_profile.seniorities'),
            value: labels(profile.seniorities, 'jobs.seniority'),
        },
        {
            label: trans('skills.search_profile.work_modes'),
            value: labels(profile.work_modes, 'jobs.work_mode'),
        },
        {
            label: trans('skills.search_profile.employment_types'),
            value: labels(profile.employment_types, 'jobs.type'),
        },
        {
            label: trans('skills.search_profile.salary_min'),
            value: profile.salary_min
                ? `${new Intl.NumberFormat(document.documentElement.lang || 'en').format(profile.salary_min)} ${profile.salary_currency ?? ''}`.trim()
                : '',
        },
    ];
});
</script>

<template>
    <section
        class="space-y-4 rounded-xl border p-4"
        aria-labelledby="search-profile-title"
    >
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="search-profile-title" class="text-base font-medium">
                        {{ trans('skills.search_profile.title') }}
                    </h2>
                    <Badge v-if="profile" variant="outline">{{
                        profile.edited
                            ? trans('skills.search_profile.edited')
                            : trans('skills.search_profile.generated')
                    }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ trans('skills.search_profile.description') }}
                </p>
            </div>
            <div v-if="profile && !editing" class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" @click="startEditing">
                    <Pencil class="size-4" aria-hidden="true" />
                    {{ trans('skills.search_profile.edit') }}
                </Button>
                <Button variant="ghost" size="sm" @click="regenerateProfile">
                    <RefreshCw class="size-4" aria-hidden="true" />
                    {{ trans('skills.search_profile.regenerate') }}
                </Button>
            </div>
        </header>

        <p v-if="!profile" class="text-sm text-muted-foreground">
            {{ trans('skills.search_profile.empty') }}
        </p>

        <template v-else-if="!editing">
            <div>
                <p class="mb-1.5 text-sm font-medium">
                    {{ trans('skills.search_profile.technologies') }}
                </p>
                <div
                    v-if="profile.technology_names.length"
                    class="flex flex-wrap gap-1.5"
                >
                    <Badge
                        v-for="name in profile.technology_names"
                        :key="name"
                        variant="secondary"
                        >{{ name }}</Badge
                    >
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    {{ trans('skills.search_profile.none') }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ trans('skills.search_profile.technologies_hint') }}
                </p>
            </div>
            <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div v-for="item in summary" :key="item.label">
                    <dt class="text-muted-foreground">{{ item.label }}</dt>
                    <dd>
                        {{ item.value || trans('skills.search_profile.none') }}
                    </dd>
                </div>
            </dl>
            <Button as-child>
                <Link :href="jobsIndex({ query: profile.listing_query })">
                    <Search class="size-4" aria-hidden="true" />
                    {{ trans('skills.search_profile.view_jobs') }}
                </Link>
            </Button>
        </template>

        <form v-else class="space-y-6" @submit.prevent="save">
            <CheckboxGroup
                v-model="form.roles"
                name="search-roles"
                :legend="trans('skills.search_profile.roles')"
                :options="roleOptions"
            />
            <InputError :message="form.errors.roles" />

            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">
                    {{ trans('skills.search_profile.technologies') }}
                </legend>
                <ul class="flex flex-wrap gap-1.5">
                    <li v-for="name in form.technologies" :key="name">
                        <Badge variant="secondary" class="gap-1 pr-1">
                            {{ name }}
                            <button
                                type="button"
                                class="rounded-full p-0.5 hover:bg-background"
                                :aria-label="
                                    trans(
                                        'skills.search_profile.remove_technology',
                                        { name },
                                    )
                                "
                                @click="
                                    form.technologies =
                                        form.technologies.filter(
                                            (item) => item !== name,
                                        )
                                "
                            >
                                <X class="size-3" aria-hidden="true" />
                            </button>
                        </Badge>
                    </li>
                </ul>
                <div class="flex max-w-sm gap-2">
                    <Label for="new-technology" class="sr-only">{{
                        trans('skills.search_profile.add_technology')
                    }}</Label>
                    <Input
                        id="new-technology"
                        v-model="newTechnology"
                        list="search-technology-suggestions"
                        maxlength="60"
                        autocomplete="off"
                        :placeholder="
                            trans('skills.search_profile.add_technology')
                        "
                        @keydown.enter.prevent="addTechnology"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        @click="addTechnology"
                        >{{
                            trans('skills.search_profile.add_technology')
                        }}</Button
                    >
                    <datalist id="search-technology-suggestions">
                        <option
                            v-for="suggestion in options.skillSuggestions"
                            :key="suggestion"
                            :value="suggestion"
                        />
                    </datalist>
                </div>
                <InputError :message="form.errors.technologies" />
            </fieldset>

            <div class="grid gap-1">
                <CheckboxGroup
                    v-model="form.seniorities"
                    name="search-seniorities"
                    :legend="trans('skills.search_profile.seniorities')"
                    :options="seniorityOptions"
                />
                <p class="text-xs text-muted-foreground">
                    {{ trans('skills.search_profile.seniorities_hint') }}
                </p>
            </div>

            <CheckboxGroup
                v-model="form.work_modes"
                name="search-work-modes"
                :legend="trans('skills.search_profile.work_modes')"
                :options="workModeOptions"
            />

            <CheckboxGroup
                v-model="form.employment_types"
                name="search-types"
                :legend="trans('skills.search_profile.employment_types')"
                :options="typeOptions"
            />

            <div class="grid max-w-md grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="search-salary">{{
                        trans('skills.search_profile.salary_min')
                    }}</Label>
                    <Input
                        id="search-salary"
                        v-model.number="form.salary_min"
                        type="number"
                        min="0"
                        inputmode="numeric"
                    />
                    <InputError :message="form.errors.salary_min" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="search-currency">{{
                        trans('skills.search_profile.currency')
                    }}</Label>
                    <NativeSelect
                        id="search-currency"
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
            </div>

            <div class="flex flex-wrap gap-2">
                <Button type="submit" :disabled="form.processing">
                    {{ trans('skills.search_profile.save') }}
                </Button>
                <Button type="button" variant="ghost" @click="editing = false">
                    {{ trans('skills.search_profile.cancel') }}
                </Button>
            </div>
        </form>
    </section>
</template>
