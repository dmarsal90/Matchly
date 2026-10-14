<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import FilterPanel from '@/features/jobs/components/FilterPanel.vue';
import JobCard from '@/features/jobs/components/JobCard.vue';
import JobGroup from '@/features/jobs/components/JobGroup.vue';
import JobListSkeleton from '@/features/jobs/components/JobListSkeleton.vue';
import { useJobFilters } from '@/features/jobs/composables/useJobFilters';
import type {
    Job,
    JobFilters,
    JobOptions,
    JobPage,
    MultiFilterKey,
} from '@/features/jobs/types';
import { Head } from '@inertiajs/vue3';
import { SearchX, SlidersHorizontal, Search } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, toRef } from 'vue';

const props = defineProps<{
    jobs: JobPage;
    groups: Record<string, number> | null;
    filters: JobFilters;
    options: JobOptions;
}>();

const { state, loading, setSearch, toggle, set, clear, goToPage, activeCount } =
    useJobFilters(toRef(props, 'filters'), props.options.default_per_page);

/** Consecutive jobs of the same group, in server order (largest group first). */
const grouped = computed(() => {
    if (!props.groups || !props.filters.group) return null;
    const key = props.filters.group as 'category' | 'role' | 'source';
    const sections: { key: string; jobs: Job[] }[] = [];

    for (const job of props.jobs.data) {
        const value = key === 'source' ? job.source.key : (job[key] ?? '');
        const last = sections[sections.length - 1];
        if (last && last.key === value) {
            last.jobs.push(job);
        } else {
            sections.push({ key: value, jobs: [job] });
        }
    }

    return sections;
});

function groupLabel(key: string): string {
    if (key === '') return trans('jobs.group.ungrouped');
    if (props.filters.group === 'source')
        return (
            props.options.source.find((source) => source.key === key)?.name ??
            key
        );

    return trans(`jobs.${props.filters.group}.${key}`);
}

function onToggle(key: MultiFilterKey, value: string): void {
    toggle(key, value);
}

function onSet(key: keyof JobFilters, value: string | number | null): void {
    set(key, value as never);
}
</script>

<template>
    <Head :title="trans('jobs.title')" />

    <div class="mx-auto w-full max-w-6xl space-y-6 py-6">
        <header class="space-y-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ trans('jobs.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ trans('jobs.description') }}
                </p>
            </div>

            <form role="search" class="relative" @submit.prevent>
                <label for="job-search" class="sr-only">{{
                    trans('jobs.search_label')
                }}</label>
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id="job-search"
                    type="search"
                    class="h-11 pl-9 text-base"
                    :placeholder="trans('jobs.search_placeholder')"
                    :model-value="state.q ?? ''"
                    autocomplete="off"
                    enterkeyhint="search"
                    @update:model-value="setSearch(String($event ?? ''))"
                />
            </form>
        </header>

        <div class="grid gap-8 lg:grid-cols-[16rem_1fr]">
            <aside class="hidden lg:block">
                <div class="sticky top-4">
                    <FilterPanel
                        :filters="state"
                        :options="options"
                        :active-count="activeCount"
                        @toggle="onToggle"
                        @set="onSet"
                        @clear="clear"
                    />
                </div>
            </aside>

            <section class="min-w-0 space-y-4" :aria-busy="loading">
                <div class="flex flex-wrap items-center gap-2">
                    <p
                        class="mr-auto text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {{
                            transChoice('jobs.results', jobs.meta.total, {
                                count: jobs.meta.total.toLocaleString(),
                            })
                        }}
                    </p>

                    <Sheet>
                        <SheetTrigger as-child>
                            <Button
                                variant="outline"
                                size="sm"
                                class="lg:hidden"
                            >
                                <SlidersHorizontal
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{ trans('jobs.filters.open') }}
                                <span
                                    v-if="activeCount"
                                    class="rounded-full bg-primary px-1.5 text-xs text-primary-foreground"
                                    >{{ activeCount }}</span
                                >
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            class="w-80 overflow-y-auto p-5"
                        >
                            <SheetHeader class="sr-only"
                                ><SheetTitle>{{
                                    trans('jobs.filters.title')
                                }}</SheetTitle></SheetHeader
                            >
                            <FilterPanel
                                :filters="state"
                                :options="options"
                                :active-count="activeCount"
                                @toggle="onToggle"
                                @set="onSet"
                                @clear="clear"
                            />
                        </SheetContent>
                    </Sheet>

                    <NativeSelect
                        :model-value="state.sort"
                        class="h-8 w-auto text-sm"
                        :aria-label="trans('jobs.sort.label')"
                        @update:model-value="onSet('sort', $event ?? 'newest')"
                    >
                        <option
                            v-for="sort in options.sort"
                            :key="sort"
                            :value="sort"
                            :disabled="sort === 'relevance' && !state.q"
                        >
                            {{ trans(`jobs.sort.${sort}`) }}
                        </option>
                    </NativeSelect>
                    <NativeSelect
                        :model-value="state.group"
                        class="h-8 w-auto text-sm"
                        :aria-label="trans('jobs.group.label')"
                        @update:model-value="onSet('group', $event ?? null)"
                    >
                        <option :value="null">
                            {{ trans('jobs.group.none') }}
                        </option>
                        <option
                            v-for="group in options.group"
                            :key="group"
                            :value="group"
                        >
                            {{ trans(`jobs.group.${group}`) }}
                        </option>
                    </NativeSelect>
                    <NativeSelect
                        :model-value="state.per_page"
                        class="h-8 w-auto text-sm"
                        :aria-label="trans('jobs.per_page')"
                        @update:model-value="onSet('per_page', Number($event))"
                    >
                        <option
                            v-for="size in options.per_page"
                            :key="size"
                            :value="size"
                        >
                            {{ size }}
                        </option>
                    </NativeSelect>
                </div>

                <JobListSkeleton v-if="loading && jobs.data.length === 0" />

                <EmptyState
                    v-else-if="jobs.data.length === 0"
                    :icon="SearchX"
                    :title="trans('jobs.empty.title')"
                    :description="trans('jobs.empty.description')"
                >
                    <Button
                        v-if="activeCount"
                        variant="outline"
                        size="sm"
                        @click="clear"
                        >{{ trans('jobs.filters.clear') }}</Button
                    >
                </EmptyState>

                <div
                    v-else
                    class="space-y-6 transition-opacity"
                    :class="loading && 'opacity-60'"
                >
                    <template v-if="grouped && groups">
                        <JobGroup
                            v-for="section in grouped"
                            :key="section.key"
                            :label="groupLabel(section.key)"
                            :count="groups[section.key] ?? section.jobs.length"
                        >
                            <JobCard
                                v-for="job in section.jobs"
                                :key="job.id"
                                :job="job"
                                @tag="onToggle('tag', $event)"
                            />
                        </JobGroup>
                    </template>
                    <div v-else class="space-y-3">
                        <JobCard
                            v-for="job in jobs.data"
                            :key="job.id"
                            :job="job"
                            @tag="onToggle('tag', $event)"
                        />
                    </div>
                </div>

                <div v-if="jobs.meta.total > 0" class="space-y-2 pt-2">
                    <Pagination
                        :current-page="jobs.meta.current_page"
                        :last-page="jobs.meta.last_page"
                        @page="goToPage"
                    />
                    <p class="text-center text-xs text-muted-foreground">
                        {{
                            trans('jobs.showing', {
                                from: String(jobs.meta.from ?? 0),
                                to: String(jobs.meta.to ?? 0),
                                total: jobs.meta.total.toLocaleString(),
                            })
                        }}
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
