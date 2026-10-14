<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import SkillLevel from '@/features/profile/components/SkillLevel.vue';
import type { MergedSkill } from '@/features/profile/types';
import { show as questionnaire } from '@/routes/onboarding';
import { Link } from '@inertiajs/vue3';
import { BadgeCheck, TriangleAlert } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';

defineProps<{ skills: MergedSkill[] }>();

function gapText(gap: number): string {
    const diff = transChoice('skills.profile.levels_unit', Math.abs(gap));

    return gap > 0
        ? trans('skills.profile.gap_above', { diff })
        : trans('skills.profile.gap_below', { diff });
}
</script>

<template>
    <div>
        <p
            v-if="skills.length === 0"
            class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ trans('skills.profile.empty') }}
        </p>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="skill in skills"
                :key="skill.skill"
                class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:gap-4"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ skill.name }}</span>
                        <Badge
                            v-if="skill.has_evidence"
                            variant="secondary"
                            class="gap-1"
                        >
                            <BadgeCheck class="size-3" aria-hidden="true" />
                            {{ trans('skills.profile.evidenced') }}
                        </Badge>
                        <Badge v-else variant="outline">{{
                            trans('skills.profile.declared')
                        }}</Badge>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        <span
                            v-for="(source, position) in skill.sources"
                            :key="source"
                            >{{ position > 0 ? ' · ' : ''
                            }}{{
                                trans(`skills.sources.${source}.title`)
                            }}</span
                        >
                        <template v-if="skill.years">
                            ·
                            {{
                                transChoice('skills.profile.years', skill.years)
                            }}</template
                        >
                        <template v-if="skill.last_used_year">
                            ·
                            {{
                                trans('skills.profile.last_used', {
                                    year: String(skill.last_used_year),
                                })
                            }}</template
                        >
                    </p>
                    <p
                        v-if="skill.level_gap && Math.abs(skill.level_gap) >= 2"
                        class="mt-1 flex flex-wrap items-center gap-1 text-xs text-amber-700 dark:text-amber-400"
                    >
                        <TriangleAlert class="size-3" aria-hidden="true" />
                        {{ gapText(skill.level_gap) }}
                        <Link
                            :href="questionnaire({ query: { step: 'skills' } })"
                            class="underline underline-offset-2"
                            >{{ trans('skills.profile.gap_fix') }}</Link
                        >
                    </p>
                </div>
                <div class="flex items-center gap-4 sm:justify-end">
                    <SkillLevel :level="skill.level" />
                    <span
                        class="w-10 text-right text-xs text-muted-foreground tabular-nums"
                        :title="trans('skills.profile.confidence')"
                    >
                        <span class="sr-only"
                            >{{ trans('skills.profile.confidence') }}:
                        </span>
                        {{ Math.round(skill.confidence * 100) }}%
                    </span>
                </div>
            </li>
        </ul>
        <p v-if="skills.length > 0" class="mt-2 text-xs text-muted-foreground">
            {{ trans('skills.profile.legend') }}
        </p>
    </div>
</template>
