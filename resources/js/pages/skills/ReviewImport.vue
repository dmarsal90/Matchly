<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import SkillLevel from '@/features/profile/components/SkillLevel.vue';
import { formatPeriod } from '@/features/profile/lib/format';
import type { ImportedSkill, ImportReview } from '@/features/profile/types';
import { index } from '@/routes/skills';
import { confirm, destroy } from '@/routes/skills/imports';
import { Head, router, useForm } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, reactive } from 'vue';

const props = defineProps<{
    review: ImportReview;
    currentHeadline: string | null;
    languageLevels: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Skills', href: index() }],
    },
});

type Section = 'positions' | 'education' | 'certifications' | 'projects';
const SECTIONS: Section[] = [
    'positions',
    'education',
    'certifications',
    'projects',
];

const data = computed(() => props.review.data);
const skills = computed(() => data.value.skills ?? []);
const allIndexes = (section: Section): number[] =>
    (data.value[section] ?? []).map((_, position) => position);

// Everything starts selected: the user removes what they do not want.
const selection = reactive({
    skills: skills.value.map((skill) => skill.skill),
    items: Object.fromEntries(
        SECTIONS.map((section) => [section, allIndexes(section)]),
    ) as Record<Section, number[]>,
    useHeadline: false,
    headline: data.value.profile?.headline ?? '',
    languages: (data.value.languages ?? []).map((language) => ({
        include: language.code !== null,
        code: language.code,
        name: language.name,
        level: language.level ?? 'B2',
    })),
});

const form = useForm({});
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function toggle<T>(
    list: T[],
    value: T,
    checked: boolean | 'indeterminate',
): T[] {
    return checked === true
        ? [...new Set([...list, value])]
        : list.filter((item) => item !== value);
}

function evidence(skill: ImportedSkill): string {
    const parts: string[] = [];
    const found = skill.evidence;
    if (found.repository_count) {
        parts.push(
            `${transChoice('skills.review.evidence.repositories', found.repository_count)}${
                found.repositories?.length
                    ? ` (${found.repositories.join(', ')})`
                    : ''
            }`,
        );
    }
    if (found.signals?.length) {
        parts.push(
            found.signals
                .map((signal) =>
                    trans(`skills.review.evidence.signals.${signal}`),
                )
                .join(', '),
        );
    }
    if (found.listed) parts.push(trans('skills.review.evidence.listed'));
    if (found.positions?.length) {
        parts.push(
            trans('skills.review.evidence.positions', {
                positions: found.positions.join('; '),
            }),
        );
    }
    if (found.mentions)
        parts.push(
            transChoice('skills.review.evidence.mentions', found.mentions),
        );
    if (skill.years)
        parts.push(transChoice('skills.profile.years', skill.years));
    if (skill.last_used_year) {
        parts.push(
            trans('skills.profile.last_used', {
                year: String(skill.last_used_year),
            }),
        );
    }

    return parts.join(' · ');
}

function period(start: string | null, end: string | null): string {
    if (!start && !end) return '';

    return `${formatPeriod(start)} – ${end ? formatPeriod(end) : trans('skills.review.present')}`;
}

function itemTitle(section: Section, position: number): string {
    const item = data.value[section]?.[position] as
        Record<string, string | null> | undefined;
    if (!item) return '';

    return {
        positions: [item.title, item.company].filter(Boolean).join(' · '),
        education: [item.school, item.degree].filter(Boolean).join(' · '),
        certifications: [item.name, item.authority].filter(Boolean).join(' · '),
        projects: item.title ?? '',
    }[section];
}

function itemDetail(section: Section, position: number): string {
    const item = data.value[section]?.[position] as
        Record<string, string | null> | undefined;

    return item ? period(item.started_on, item.finished_on) : '';
}

function submit(): void {
    form.transform(() => ({
        skills: selection.skills,
        items: selection.items,
        headline: selection.useHeadline ? selection.headline : null,
        languages: selection.languages
            .filter((language) => language.include && language.code)
            .map((language) => ({
                code: language.code,
                level: language.level,
            })),
    })).post(confirm.url(props.review.id));
}

function discard(): void {
    router.delete(destroy.url(props.review.id));
}

const sourceLabel = computed(() =>
    trans(`skills.review.source.${props.review.source}`, {
        reference: props.review.reference ?? '',
    }),
);
</script>

<template>
    <Head :title="trans('skills.review.title')" />

    <div class="mx-auto w-full max-w-3xl space-y-8 px-4 py-6">
        <div>
            <Heading
                :title="trans('skills.review.title')"
                :description="trans('skills.review.description')"
            />
            <p class="-mt-6 text-sm font-medium">{{ sourceLabel }}</p>
            <p v-if="data.files" class="mt-1 text-xs text-muted-foreground">
                {{
                    trans('skills.review.files_read', {
                        files: data.files.join(', '),
                        skipped: String(data.skipped_files ?? 0),
                    })
                }}
            </p>
        </div>

        <form class="space-y-10" @submit.prevent="submit">
            <!-- Headline (LinkedIn) -->
            <section
                v-if="data.profile?.headline"
                class="space-y-3"
                aria-labelledby="review-headline"
            >
                <h2 id="review-headline" class="text-base font-medium">
                    {{ trans('skills.review.headline') }}
                </h2>
                <div class="flex items-center gap-2">
                    <Checkbox
                        id="use-headline"
                        v-model="selection.useHeadline"
                    />
                    <Label for="use-headline" class="font-normal">{{
                        trans('skills.review.use_headline')
                    }}</Label>
                </div>
                <Input
                    v-if="selection.useHeadline"
                    v-model="selection.headline"
                    maxlength="120"
                    :aria-label="trans('skills.review.headline')"
                />
                <p v-if="currentHeadline" class="text-xs text-muted-foreground">
                    {{
                        trans('skills.review.current_headline', {
                            headline: currentHeadline,
                        })
                    }}
                </p>
            </section>

            <!-- Skills -->
            <section class="space-y-3" aria-labelledby="review-skills">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="review-skills" class="text-base font-medium">
                        {{ trans('skills.review.skills') }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >({{ selection.skills.length }}/{{
                                skills.length
                            }})</span
                        >
                    </h2>
                    <div v-if="skills.length" class="flex gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="
                                selection.skills = skills.map(
                                    (skill) => skill.skill,
                                )
                            "
                            >{{ trans('skills.review.select_all') }}</Button
                        >
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="selection.skills = []"
                            >{{ trans('skills.review.select_none') }}</Button
                        >
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ trans('skills.review.skills_hint') }}
                </p>
                <p v-if="!skills.length" class="text-sm text-muted-foreground">
                    {{ trans('skills.review.nothing') }}
                </p>
                <ul v-else class="divide-y rounded-lg border">
                    <li
                        v-for="skill in skills"
                        :key="skill.skill"
                        class="flex items-start gap-3 p-3"
                    >
                        <Checkbox
                            :id="`skill-${skill.skill}`"
                            class="mt-0.5"
                            :model-value="
                                selection.skills.includes(skill.skill)
                            "
                            @update:model-value="
                                selection.skills = toggle(
                                    selection.skills,
                                    skill.skill,
                                    $event,
                                )
                            "
                        />
                        <div class="min-w-0 flex-1">
                            <Label
                                :for="`skill-${skill.skill}`"
                                class="font-medium"
                                >{{ skill.name }}</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                {{ evidence(skill) }}
                            </p>
                        </div>
                        <SkillLevel :level="skill.level" />
                    </li>
                </ul>
                <InputError :message="errors.skills" />
            </section>

            <!-- Positions, education, certifications, projects (LinkedIn) -->
            <template v-for="section in SECTIONS" :key="section">
                <section
                    v-if="data[section]?.length"
                    class="space-y-3"
                    :aria-labelledby="`review-${section}`"
                >
                    <h2 :id="`review-${section}`" class="text-base font-medium">
                        {{ trans(`skills.review.${section}`) }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >({{ selection.items[section].length }}/{{
                                data[section]?.length
                            }})</span
                        >
                    </h2>
                    <ul class="divide-y rounded-lg border">
                        <li
                            v-for="(_, position) in data[section]"
                            :key="position"
                            class="flex items-start gap-3 p-3"
                        >
                            <Checkbox
                                :id="`${section}-${position}`"
                                class="mt-0.5"
                                :model-value="
                                    selection.items[section].includes(position)
                                "
                                @update:model-value="
                                    selection.items[section] = toggle(
                                        selection.items[section],
                                        position,
                                        $event,
                                    )
                                "
                            />
                            <div class="min-w-0 flex-1">
                                <Label
                                    :for="`${section}-${position}`"
                                    class="font-medium"
                                    >{{ itemTitle(section, position) }}</Label
                                >
                                <p
                                    v-if="itemDetail(section, position)"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ itemDetail(section, position) }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </section>
            </template>

            <!-- Languages (LinkedIn) -->
            <section
                v-if="selection.languages.length"
                class="space-y-3"
                aria-labelledby="review-languages"
            >
                <h2 id="review-languages" class="text-base font-medium">
                    {{ trans('skills.review.languages') }}
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ trans('skills.review.languages_hint') }}
                </p>
                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="(language, position) in selection.languages"
                        :key="position"
                        class="flex flex-wrap items-center gap-3 p-3"
                    >
                        <Checkbox
                            :id="`language-${position}`"
                            v-model="language.include"
                            :disabled="!language.code"
                        />
                        <Label
                            :for="`language-${position}`"
                            class="min-w-32 flex-1 font-medium"
                        >
                            {{ language.name }}
                            <span
                                v-if="!language.code"
                                class="text-xs font-normal text-muted-foreground"
                            >
                                · {{ trans('skills.review.unknown_language') }}
                            </span>
                        </Label>
                        <NativeSelect
                            v-if="language.code"
                            v-model="language.level"
                            class="w-44"
                            :aria-label="trans('profile.fields.level')"
                        >
                            <option
                                v-for="level in languageLevels"
                                :key="level"
                                :value="level"
                            >
                                {{ trans(`profile.levels.${level}`) }}
                            </option>
                        </NativeSelect>
                    </li>
                </ul>
            </section>

            <!-- Repositories (GitHub): what the evidence comes from -->
            <section
                v-if="data.repositories?.length"
                class="space-y-3"
                aria-labelledby="review-repositories"
            >
                <h2 id="review-repositories" class="text-base font-medium">
                    {{ trans('skills.review.repositories') }}
                </h2>
                <ul class="divide-y rounded-lg border text-sm">
                    <li
                        v-for="repository in data.repositories"
                        :key="repository.name"
                        class="p-3"
                    >
                        <a
                            :href="repository.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium underline-offset-2 hover:underline"
                            >{{ repository.name }}</a
                        >
                        <p
                            v-if="repository.description"
                            class="text-xs text-muted-foreground"
                        >
                            {{ repository.description }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                Object.keys(repository.languages)
                                    .slice(0, 5)
                                    .join(', ')
                            }}
                        </p>
                    </li>
                </ul>
            </section>

            <InputError :message="errors.import" />

            <div
                class="flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:justify-between"
            >
                <Button
                    type="button"
                    variant="ghost"
                    class="text-destructive hover:text-destructive"
                    @click="discard"
                >
                    {{ trans('skills.review.discard') }}
                </Button>
                <Button type="submit" :disabled="form.processing">{{
                    trans('skills.review.confirm')
                }}</Button>
            </div>
        </form>
    </div>
</template>
