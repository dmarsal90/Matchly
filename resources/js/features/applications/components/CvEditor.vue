<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import ResumeDiff from '@/features/applications/components/ResumeDiff.vue';
import {
    diffSequence,
    diffWords,
    hasChanges,
} from '@/features/applications/lib/diff';
import type { ContentSource, Resume } from '@/features/applications/types';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{
    master: Resume | null;
    source: ContentSource | null;
    missingKeywords: string[];
    readonly?: boolean;
}>();

const resume = defineModel<Resume>({ required: true });
const showChanges = ref(false);

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const lines = (text: string): string[] =>
    text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);

const skillsText = computed({
    get: () => resume.value.skills.join('\n'),
    set: (value: string) => (resume.value.skills = lines(value)),
});

function setBullets(index: number, value: string): void {
    resume.value.experience[index].bullets = lines(value);
}

const changes = computed(() => {
    const master = props.master;
    if (!master) return null;

    return {
        summary: diffWords(
            master.summary ?? master.headline ?? '',
            resume.value.summary ?? '',
        ),
        skills: diffSequence(master.skills, resume.value.skills),
        experience: resume.value.experience.map((position, index) =>
            diffSequence(
                master.experience[index]?.bullets ?? [],
                position.bullets,
            ),
        ),
    };
});

const anyChange = computed(
    () =>
        !!changes.value &&
        (hasChanges(changes.value.summary) ||
            hasChanges(changes.value.skills) ||
            changes.value.experience.some((parts) => hasChanges(parts))),
);
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Badge v-if="source" variant="outline">{{
                trans(`quick_apply.package.source.${source}`)
            }}</Badge>
            <Button
                v-if="master"
                type="button"
                variant="ghost"
                size="sm"
                :aria-expanded="showChanges"
                @click="showChanges = !showChanges"
            >
                {{
                    showChanges
                        ? trans('quick_apply.editor.hide_changes')
                        : trans('quick_apply.editor.show_changes')
                }}
            </Button>
        </div>

        <div v-if="resume.keywords.length" class="grid gap-1.5">
            <p class="text-sm font-medium">
                {{ trans('quick_apply.editor.keywords') }}
            </p>
            <ul class="flex flex-wrap gap-1.5">
                <li v-for="keyword in resume.keywords" :key="keyword">
                    <Badge variant="secondary">{{ keyword }}</Badge>
                </li>
            </ul>
        </div>
        <div v-if="missingKeywords.length" class="grid gap-1.5">
            <p class="text-xs text-muted-foreground">
                {{ trans('quick_apply.editor.missing_keywords') }}
            </p>
            <ul class="flex flex-wrap gap-1.5">
                <li v-for="keyword in missingKeywords" :key="keyword">
                    <Badge variant="outline" class="text-muted-foreground">{{
                        keyword
                    }}</Badge>
                </li>
            </ul>
        </div>

        <div
            v-if="showChanges && changes"
            class="space-y-4 rounded-lg border bg-muted/30 p-3"
        >
            <p v-if="!anyChange" class="text-sm text-muted-foreground">
                {{ trans('quick_apply.editor.no_changes') }}
            </p>
            <template v-else>
                <div v-if="hasChanges(changes.summary)">
                    <p class="mb-1 text-xs font-medium text-muted-foreground">
                        {{ trans('quick_apply.editor.summary') }}
                    </p>
                    <ResumeDiff :parts="changes.summary" inline />
                </div>
                <div v-if="hasChanges(changes.skills)">
                    <p class="mb-1 text-xs font-medium text-muted-foreground">
                        {{ trans('quick_apply.editor.skills') }}
                    </p>
                    <ResumeDiff :parts="changes.skills" />
                </div>
                <template
                    v-for="(parts, index) in changes.experience"
                    :key="index"
                >
                    <div v-if="hasChanges(parts)">
                        <p
                            class="mb-1 text-xs font-medium text-muted-foreground"
                        >
                            {{ resume.experience[index].title
                            }}<template v-if="resume.experience[index].company">
                                ·
                                {{ resume.experience[index].company }}</template
                            >
                        </p>
                        <ResumeDiff :parts="parts" />
                    </div>
                </template>
            </template>
        </div>

        <div class="grid gap-2">
            <Label for="cv-summary">{{
                trans('quick_apply.editor.summary')
            }}</Label>
            <textarea
                id="cv-summary"
                v-model="resume.summary"
                :readonly="readonly"
                rows="4"
                maxlength="1200"
                :class="textareaClass"
            />
        </div>

        <div class="grid gap-2">
            <Label for="cv-skills">{{
                trans('quick_apply.editor.skills')
            }}</Label>
            <textarea
                id="cv-skills"
                v-model="skillsText"
                :readonly="readonly"
                rows="6"
                :class="textareaClass"
            />
        </div>

        <div
            v-for="(position, index) in resume.experience"
            :key="index"
            class="grid gap-2"
        >
            <Label :for="`cv-position-${index}`">
                {{ position.title
                }}<template v-if="position.company">
                    · {{ position.company }}</template
                >
                <span class="font-normal text-muted-foreground">
                    — {{ trans('quick_apply.editor.bullets') }}</span
                >
            </Label>
            <textarea
                :id="`cv-position-${index}`"
                :value="position.bullets.join('\n')"
                :readonly="readonly"
                rows="4"
                :class="textareaClass"
                @change="
                    setBullets(
                        index,
                        ($event.target as HTMLTextAreaElement).value,
                    )
                "
            />
        </div>
    </div>
</template>
