<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index as insightsIndex } from '@/routes/insights';
import { index as skillsIndex } from '@/routes/skills';
import { Head, Link } from '@inertiajs/vue3';
import { Lightbulb, Target } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

type SkillGap = {
    skill: string;
    name: string;
    jobs: number;
    required: number;
    tracked: number;
    only_gap: number;
};

const props = defineProps<{
    skillGaps: { jobs: number; tracked: number; skills: SkillGap[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'nav.insights', href: insightsIndex() }] },
});

const top = computed(() => props.skillGaps.skills[0] ?? null);
const max = computed(() =>
    Math.max(1, ...props.skillGaps.skills.map((item) => item.jobs)),
);
</script>

<template>
    <Head :title="trans('insights.title')" />

    <div class="mx-auto w-full max-w-4xl space-y-8 px-4 py-6">
        <Heading
            :title="trans('insights.title')"
            :description="trans('insights.description')"
        />

        <section class="space-y-4" aria-labelledby="skill-gaps">
            <div>
                <h2 id="skill-gaps" class="text-lg font-semibold">
                    {{ trans('insights.skill_gaps.title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ trans('insights.skill_gaps.description') }}
                </p>
            </div>

            <EmptyState
                v-if="!top"
                :icon="Target"
                :title="trans('insights.skill_gaps.empty')"
            >
                <Button as-child size="sm"
                    ><Link :href="skillsIndex()">{{
                        trans('nav.skills')
                    }}</Link></Button
                >
            </EmptyState>

            <template v-else>
                <div
                    class="flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4"
                >
                    <Lightbulb
                        class="mt-0.5 size-5 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div class="space-y-1">
                        <p class="font-medium">
                            {{
                                trans('insights.skill_gaps.next', {
                                    skill: top.name,
                                })
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                transChoice(
                                    'insights.skill_gaps.next_hint',
                                    top.only_gap,
                                    {
                                        count: String(top.only_gap),
                                        jobs: String(top.jobs),
                                    },
                                )
                            }}
                        </p>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    {{
                        trans('insights.skill_gaps.basis', {
                            jobs: String(skillGaps.jobs),
                            tracked: String(skillGaps.tracked),
                        })
                    }}
                </p>

                <table class="w-full text-sm">
                    <caption class="sr-only">
                        {{
                            trans('insights.skill_gaps.table')
                        }}
                    </caption>
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th scope="col" class="py-2 pr-3 font-medium">
                                {{ trans('insights.skill_gaps.skill') }}
                            </th>
                            <th scope="col" class="w-1/3 py-2 pr-3 font-medium">
                                {{ trans('insights.skill_gaps.jobs') }}
                            </th>
                            <th
                                scope="col"
                                class="py-2 pr-3 text-right font-medium"
                            >
                                {{ trans('insights.skill_gaps.required') }}
                            </th>
                            <th
                                scope="col"
                                class="py-2 pr-3 text-right font-medium"
                            >
                                {{ trans('insights.skill_gaps.only_gap') }}
                            </th>
                            <th scope="col" class="py-2 text-right font-medium">
                                {{ trans('insights.skill_gaps.tracked') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="item in skillGaps.skills" :key="item.skill">
                            <th
                                scope="row"
                                class="py-2 pr-3 text-left font-medium"
                            >
                                {{ item.name }}
                            </th>
                            <td class="py-2 pr-3">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-2 rounded-full bg-primary"
                                        :style="{
                                            width: `${(item.jobs / max) * 100}%`,
                                            minWidth: '4px',
                                        }"
                                        aria-hidden="true"
                                    />
                                    <span class="tabular-nums">{{
                                        item.jobs
                                    }}</span>
                                </div>
                            </td>
                            <td class="py-2 pr-3 text-right tabular-nums">
                                {{ item.required }}
                            </td>
                            <td class="py-2 pr-3 text-right tabular-nums">
                                {{ item.only_gap }}
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ item.tracked }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>
        </section>
    </div>
</template>
