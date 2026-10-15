<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import GithubConnect from '@/features/profile/components/GithubConnect.vue';
import LinkedInImport from '@/features/profile/components/LinkedInImport.vue';
import MergedSkillList from '@/features/profile/components/MergedSkillList.vue';
import SearchProfileCard from '@/features/profile/components/SearchProfileCard.vue';
import SourceCard from '@/features/profile/components/SourceCard.vue';
import type {
    GithubState,
    MergedSkill,
    SearchProfile,
    SkillSource,
    SkillsOptions,
    SourceState,
} from '@/features/profile/types';
import { edit as editCareer } from '@/routes/career';
import { show as questionnaire } from '@/routes/onboarding';
import { index } from '@/routes/skills';
import { cv as analyzeCv } from '@/routes/skills/imports';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import {
    ClipboardList,
    FileArchive,
    FileText,
    FolderGit2,
    Sparkles,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    skills: MergedSkill[];
    sources: Record<SkillSource, SourceState>;
    onboardingCompleted: boolean;
    github: GithubState;
    cv: { ready: boolean };
    searchProfile: SearchProfile | null;
    options: SkillsOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Skills', href: index() }],
    },
});

const page = usePage();
const cvError = computed(() => {
    const errors = page.props.errors as Record<string, string>;

    return errors.cv ?? errors.ai_consent;
});
const analyzing = ref(false);
const useAi = ref(false);
const aiConsent = ref(false);

function analyze(): void {
    router.post(
        analyzeCv.url(),
        { use_ai: useAi.value, ai_consent: aiConsent.value },
        {
            preserveScroll: true,
            onStart: () => (analyzing.value = true),
            onFinish: () => (analyzing.value = false),
        },
    );
}

// GitHub imports run in the queue: poll only while one is being read.
const processing = computed(() =>
    Object.values(props.sources).some(
        (source) => source.pending?.status === 'processing',
    ),
);
const { start, stop } = usePoll(
    3000,
    { only: ['sources', 'skills', 'searchProfile'] },
    { autoStart: false },
);
watch(processing, (value) => (value ? start() : stop()), { immediate: true });
</script>

<template>
    <Head :title="trans('skills.title')" />

    <div class="mx-auto w-full max-w-5xl space-y-10 px-4 py-6">
        <Heading
            :title="trans('skills.title')"
            :description="trans('skills.description')"
        />

        <section
            v-if="!onboardingCompleted"
            class="flex flex-col gap-4 rounded-xl border border-primary/30 bg-primary/5 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <Sparkles
                    class="mt-0.5 size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div>
                    <h2 class="font-medium">
                        {{ trans('skills.questionnaire_cta.title') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ trans('skills.questionnaire_cta.description') }}
                    </p>
                </div>
            </div>
            <Button as-child>
                <Link :href="questionnaire()">{{
                    trans('skills.questionnaire_cta.action')
                }}</Link>
            </Button>
        </section>

        <section class="space-y-4" aria-labelledby="skills-profile-title">
            <h2 id="skills-profile-title" class="text-base font-medium">
                {{ trans('skills.profile.title') }}
            </h2>
            <MergedSkillList :skills="skills" />
        </section>

        <SearchProfileCard :profile="searchProfile" :options="options" />

        <section class="space-y-4" aria-labelledby="sources-title">
            <h2 id="sources-title" class="text-base font-medium">
                {{ trans('skills.sources.title') }}
            </h2>

            <div class="grid gap-4 lg:grid-cols-2">
                <SourceCard
                    source="declared"
                    :state="sources.declared"
                    :icon="ClipboardList"
                >
                    <div>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="questionnaire()">{{
                                onboardingCompleted
                                    ? trans('skills.sources.declared.edit')
                                    : trans('skills.sources.declared.start')
                            }}</Link>
                        </Button>
                    </div>
                </SourceCard>

                <SourceCard source="cv" :state="sources.cv" :icon="FileText">
                    <div class="grid gap-2">
                        <div v-if="cv.ready" class="grid gap-3">
                            <div class="flex items-start gap-2">
                                <Checkbox
                                    id="cv-use-ai"
                                    v-model="useAi"
                                    class="mt-0.5"
                                />
                                <Label
                                    for="cv-use-ai"
                                    class="leading-snug font-normal"
                                    >{{
                                        trans('skills.sources.cv.use_ai')
                                    }}</Label
                                >
                            </div>
                            <div v-if="useAi" class="flex items-start gap-2">
                                <Checkbox
                                    id="cv-ai-consent"
                                    v-model="aiConsent"
                                    class="mt-0.5"
                                />
                                <Label
                                    for="cv-ai-consent"
                                    class="text-xs leading-snug font-normal text-muted-foreground"
                                    >{{
                                        trans('skills.sources.cv.ai_consent')
                                    }}</Label
                                >
                            </div>
                            <div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="analyzing"
                                    @click="analyze"
                                >
                                    {{
                                        sources.cv.confirmed
                                            ? trans('skills.sources.reimport')
                                            : trans('skills.sources.cv.analyze')
                                    }}
                                </Button>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ trans('skills.sources.cv.missing') }}
                            <Link
                                :href="editCareer()"
                                class="font-medium underline underline-offset-2"
                                >{{
                                    trans('skills.sources.cv.go_to_profile')
                                }}</Link
                            >
                        </p>
                        <InputError :message="cvError" />
                    </div>
                </SourceCard>

                <SourceCard
                    source="github"
                    :state="sources.github"
                    :icon="FolderGit2"
                >
                    <GithubConnect
                        :state="github"
                        :consented="sources.github.consented"
                        :busy="sources.github.pending?.status === 'processing'"
                    />
                </SourceCard>

                <SourceCard
                    source="linkedin"
                    :state="sources.linkedin"
                    :icon="FileArchive"
                >
                    <LinkedInImport
                        :max-kilobytes="options.linkedinMaxKilobytes"
                        :export-url="options.linkedinExportUrl"
                        :consented="sources.linkedin.consented"
                    />
                </SourceCard>
            </div>
        </section>
    </div>
</template>
