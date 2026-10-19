<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import MatchScore from '@/features/jobs/components/MatchScore.vue';
import { relativeTime } from '@/features/jobs/lib/format';
import type {
    FitDimension,
    JobMatch,
    MatchCategory,
    MatchMessage,
} from '@/features/jobs/types';
import { show as questionnaire } from '@/routes/onboarding';
import { Link } from '@inertiajs/vue3';
import { BadgeCheck, CircleAlert, Lightbulb } from '@lucide/vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ match: JobMatch | null; signedIn: boolean }>();

const CATEGORIES: MatchCategory[] = [
    'skills',
    'experience',
    'role',
    'location',
    'languages',
    'salary',
    'education',
];

const DIMENSIONS: FitDimension[] = [
    'match',
    'north_star',
    'compensation',
    'culture',
    'red_flags',
];

const VERDICT_FLAGS = ['missing_required', 'possibly_stale'];

const languageNames = computed(
    () => new Intl.DisplayNames([getActiveLanguage()], { type: 'language' }),
);

/** Message params are data; language codes are shown by name. */
function message(group: string, item: MatchMessage): string {
    const params = { ...item.params };
    if (params.language) {
        params.language =
            languageNames.value.of(params.language) ?? params.language;
    }

    return trans(`matching.${group}.${item.key}`, params);
}

const computedAt = computed(() =>
    props.match
        ? relativeTime(props.match.computed_at, getActiveLanguage())
        : '',
);
</script>

<template>
    <section
        class="space-y-5 rounded-xl border p-4"
        aria-labelledby="match-title"
    >
        <h2 id="match-title" class="font-semibold">
            {{ trans('matching.title') }}
        </h2>

        <p v-if="!signedIn" class="text-sm text-muted-foreground">
            {{ trans('matching.sign_in') }}
        </p>

        <div
            v-else-if="!match"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                {{ trans('matching.no_profile') }}
            </p>
            <Button size="sm" as-child>
                <Link :href="questionnaire()">{{
                    trans('matching.no_profile_action')
                }}</Link>
            </Button>
        </div>

        <template v-else>
            <div class="flex items-center gap-4">
                <MatchScore :match="match" size="lg" />
                <div class="space-y-1 text-sm">
                    <p class="text-muted-foreground">
                        {{ trans('matching.explanation') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            trans('matching.computed_at', { time: computedAt })
                        }}
                    </p>
                </div>
            </div>

            <div
                v-if="!match.eligible"
                class="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm"
                role="alert"
            >
                <p class="mb-1 flex items-center gap-2 font-medium">
                    <CircleAlert class="size-4" aria-hidden="true" />
                    {{ trans('matching.ineligible.title') }}
                </p>
                <ul class="list-disc space-y-0.5 pl-6">
                    <li
                        v-for="reason in match.ineligible_reasons"
                        :key="reason.key"
                    >
                        {{ message('ineligible', reason) }}
                    </li>
                </ul>
            </div>

            <div class="rounded-lg border p-3" data-testid="verdict">
                <h3 class="mb-1 text-sm font-medium">
                    {{ trans('matching.verdict.title') }}:
                    <span class="tabular-nums">{{
                        match.verdict.grade.toFixed(1)
                    }}</span>
                    {{ trans('matching.verdict.out_of') }} ·
                    {{
                        trans(
                            `matching.verdict.recommendation.${match.verdict.recommendation}`,
                        )
                    }}
                </h3>
                <dl class="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
                    <div
                        v-for="dimension in DIMENSIONS"
                        :key="dimension"
                        class="flex justify-between gap-3"
                    >
                        <dt>
                            {{
                                trans(
                                    `matching.verdict.dimensions.${dimension}`,
                                )
                            }}
                        </dt>
                        <dd class="text-muted-foreground tabular-nums">
                            {{
                                match.verdict.dimensions[
                                    dimension
                                ].grade?.toFixed(1) ??
                                trans('matching.verdict.unknown')
                            }}
                        </dd>
                    </div>
                </dl>
                <ul class="mt-2 list-disc pl-5 text-sm text-destructive">
                    <template v-for="flag in VERDICT_FLAGS" :key="flag">
                        <li v-if="match.verdict.flags.includes(flag)">
                            {{ trans(`matching.verdict.flags.${flag}`) }}
                        </li>
                    </template>
                </ul>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ trans('matching.verdict.note') }}
                </p>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-medium">
                    {{ trans('matching.breakdown') }}
                </h3>
                <dl class="grid gap-2">
                    <div
                        v-for="category in CATEGORIES"
                        :key="category"
                        class="grid grid-cols-[9rem_1fr_3.5rem] items-center gap-3 text-sm"
                    >
                        <dt>{{ trans(`matching.categories.${category}`) }}</dt>
                        <dd class="h-2 overflow-hidden rounded-full bg-muted">
                            <span
                                class="block h-full rounded-full"
                                :class="
                                    match.breakdown[category].known
                                        ? 'bg-primary'
                                        : 'bg-muted-foreground/40'
                                "
                                :style="{
                                    width: `${Math.round(match.breakdown[category].ratio * 100)}%`,
                                }"
                            />
                        </dd>
                        <dd
                            class="text-right text-xs text-muted-foreground tabular-nums"
                            :title="
                                match.breakdown[category].known
                                    ? undefined
                                    : trans('matching.unknown')
                            "
                        >
                            {{
                                Math.round(match.breakdown[category].points)
                            }}/{{ Math.round(match.breakdown[category].max) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div v-if="match.matched_skills.length">
                    <h3 class="mb-2 text-sm font-medium">
                        {{ trans('matching.matched') }}
                    </h3>
                    <ul class="flex flex-wrap gap-1.5">
                        <li
                            v-for="skill in match.matched_skills"
                            :key="skill.skill"
                        >
                            <Badge
                                :variant="
                                    skill.evidenced ? 'secondary' : 'outline'
                                "
                                class="gap-1"
                                :title="`${skill.required ? trans('matching.required') : trans('matching.nice')} · ${skill.evidenced ? trans('matching.evidenced') : trans('matching.declared')}`"
                            >
                                <BadgeCheck
                                    v-if="skill.evidenced"
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                {{ skill.name }}
                                <span class="sr-only"
                                    >({{
                                        skill.evidenced
                                            ? trans('matching.evidenced')
                                            : trans('matching.declared')
                                    }})</span
                                >
                            </Badge>
                        </li>
                    </ul>
                </div>
                <div v-if="match.missing_skills.length">
                    <h3 class="mb-2 text-sm font-medium">
                        {{ trans('matching.missing') }}
                    </h3>
                    <ul class="flex flex-wrap gap-1.5">
                        <li
                            v-for="skill in match.missing_skills"
                            :key="skill.skill"
                        >
                            <Badge
                                variant="outline"
                                :class="
                                    skill.required
                                        ? 'border-destructive/50 text-destructive'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ skill.name }}
                                <span class="sr-only"
                                    >({{
                                        skill.required
                                            ? trans('matching.required')
                                            : trans('matching.nice')
                                    }})</span
                                >
                            </Badge>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-if="match.suggestions.length">
                <h3 class="mb-2 flex items-center gap-2 text-sm font-medium">
                    <Lightbulb class="size-4" aria-hidden="true" />
                    {{ trans('matching.suggestions_title') }}
                </h3>
                <ul
                    class="list-disc space-y-1 pl-6 text-sm text-muted-foreground"
                >
                    <li
                        v-for="(suggestion, index) in match.suggestions"
                        :key="index"
                    >
                        {{ message('suggestions', suggestion) }}
                    </li>
                </ul>
            </div>
        </template>
    </section>
</template>
