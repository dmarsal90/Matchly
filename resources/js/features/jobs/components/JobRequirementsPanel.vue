<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { JobRequirements } from '@/features/jobs/types';
import { getActiveLanguage, trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ requirements: JobRequirements }>();

const languageNames = computed(
    () => new Intl.DisplayNames([getActiveLanguage()], { type: 'language' }),
);
const asks = computed(() =>
    (
        Object.keys(
            props.requirements.asks_for,
        ) as (keyof JobRequirements['asks_for'])[]
    ).filter((key) => props.requirements.asks_for[key]),
);
const utc = (offset: number): string =>
    offset === 0 ? 'UTC' : `UTC${offset > 0 ? '+' : '−'}${Math.abs(offset)}`;
const timezone = computed(() => {
    const { utc_offset_min: min, utc_offset_max: max } =
        props.requirements.location;
    if (min === null) return null;

    return max !== null && max !== min ? `${utc(min)} … ${utc(max)}` : utc(min);
});
const empty = computed(
    () =>
        !props.requirements.required_skills.length &&
        !props.requirements.nice_to_have_skills.length &&
        props.requirements.min_years_experience === null &&
        !props.requirements.languages.length &&
        !props.requirements.education.degree_required &&
        !asks.value.length,
);
</script>

<template>
    <section
        class="space-y-4 rounded-xl border p-4"
        aria-labelledby="requirements-title"
    >
        <div>
            <h2 id="requirements-title" class="font-semibold">
                {{ trans('matching.requirements.title') }}
            </h2>
            <p class="text-xs text-muted-foreground">
                {{
                    requirements.source === 'ai'
                        ? trans('matching.requirements.source_ai')
                        : trans('matching.requirements.source_rules')
                }}
            </p>
        </div>

        <p v-if="empty" class="text-sm text-muted-foreground">
            {{ trans('matching.requirements.none') }}
        </p>

        <div v-if="requirements.required_skills.length">
            <h3 class="mb-1.5 text-sm font-medium">
                {{ trans('matching.requirements.required_skills') }}
            </h3>
            <ul class="flex flex-wrap gap-1.5">
                <li
                    v-for="skill in requirements.required_skills"
                    :key="skill.skill"
                >
                    <Badge variant="secondary">
                        {{ skill.name
                        }}<template v-if="skill.min_years">
                            · {{ skill.min_years }}+</template
                        >
                    </Badge>
                </li>
            </ul>
        </div>

        <div v-if="requirements.nice_to_have_skills.length">
            <h3 class="mb-1.5 text-sm font-medium">
                {{ trans('matching.requirements.nice_skills') }}
            </h3>
            <ul class="flex flex-wrap gap-1.5">
                <li
                    v-for="skill in requirements.nice_to_have_skills"
                    :key="skill.skill"
                >
                    <Badge variant="outline">{{ skill.name }}</Badge>
                </li>
            </ul>
        </div>

        <ul class="space-y-1 text-sm">
            <li v-if="requirements.min_years_experience !== null">
                {{
                    transChoice(
                        'matching.requirements.years',
                        requirements.min_years_experience,
                    )
                }}
            </li>
            <li v-if="requirements.languages.length">
                {{ trans('matching.requirements.languages') }}:
                {{
                    requirements.languages
                        .map(
                            (language) =>
                                `${languageNames.of(language.code) ?? language.code}${language.level ? ` (${language.level})` : ''}`,
                        )
                        .join(', ')
                }}
            </li>
            <li v-if="requirements.education.degree_required">
                {{ trans('matching.requirements.degree')
                }}<template v-if="requirements.education.field">
                    ({{ requirements.education.field }})</template
                >
            </li>
            <li v-if="requirements.certifications.length">
                {{ trans('matching.requirements.certifications') }}:
                {{ requirements.certifications.join(', ') }}
            </li>
            <li v-if="timezone">
                {{
                    trans('matching.requirements.timezone', { range: timezone })
                }}
            </li>
            <li v-if="requirements.location.exclusive">
                {{ trans('matching.requirements.exclusive') }}
            </li>
        </ul>

        <div v-if="asks.length">
            <h3 class="mb-1.5 text-sm font-medium">
                {{ trans('matching.requirements.asks_for') }}
            </h3>
            <ul class="flex flex-wrap gap-1.5">
                <li v-for="key in asks" :key="key">
                    <Badge variant="outline">{{
                        trans(`matching.requirements.asks.${key}`)
                    }}</Badge>
                </li>
            </ul>
        </div>
    </section>
</template>
