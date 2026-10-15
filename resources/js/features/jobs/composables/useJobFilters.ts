import type { JobFilters, MultiFilterKey } from '@/features/jobs/types';
import { index } from '@/routes/jobs';
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import type { Ref } from 'vue';
import { computed, reactive, ref, watch } from 'vue';

/** Props are reactive proxies (structuredClone rejects them); filters are plain JSON data. */
const copy = (filters: JobFilters): JobFilters =>
    JSON.parse(JSON.stringify(filters)) as JobFilters;

const MULTI_KEYS: MultiFilterKey[] = [
    'work_mode',
    'scope',
    'type',
    'role',
    'category',
    'seniority',
    'source',
    'tag',
];
/** The recommended tab is ordered by score; everything else by date. */
export const defaultSort = (filters: Pick<JobFilters, 'tab'>): string =>
    filters.tab === 'recommended' ? 'match' : 'newest';

/** Query string for the current state: empty values and defaults are left out so URLs stay short. */
export function toQuery(
    filters: JobFilters,
    defaultPerPage: number,
    page = 1,
): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.q?.trim()) query.q = filters.q.trim();
    for (const key of MULTI_KEYS) {
        if (filters[key].length > 0) query[key] = filters[key].join(',');
    }
    if (filters.posted) query.posted = filters.posted;
    if (filters.from) query.from = filters.from;
    if (filters.to) query.to = filters.to;
    if (filters.salary_min) query.salary_min = String(filters.salary_min);
    if (filters.salary_min && filters.currency)
        query.currency = filters.currency;
    if (filters.sort && filters.sort !== defaultSort(filters))
        query.sort = filters.sort;
    if (filters.tab) query.tab = filters.tab;
    if (filters.min_score) query.min_score = String(filters.min_score);
    if (filters.tab && filters.ineligible) query.ineligible = '1';
    if (filters.group) query.group = filters.group;
    if (filters.per_page !== defaultPerPage)
        query.per_page = String(filters.per_page);
    if (page > 1) query.page = String(page);

    return query;
}

/**
 * Listing state mirrored in the URL: every change is an Inertia visit that
 * replaces the history entry and only reloads the result props.
 */
export function useJobFilters(server: Ref<JobFilters>, defaultPerPage: number) {
    const state = reactive<JobFilters>(copy(server.value));
    const loading = ref(false);

    // Back/forward navigation or links bring new server state: adopt it.
    watch(server, (value) => Object.assign(state, copy(value)));

    function visit(page = 1): void {
        router.get(index.url(), toQuery(state, defaultPerPage, page), {
            preserveState: true,
            preserveScroll: page === 1,
            replace: true,
            only: ['jobs', 'groups', 'filters', 'ineligible'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        });
    }

    const visitDebounced = useDebounceFn(() => visit(), 350);

    function setSearch(value: string): void {
        state.q = value;
        void visitDebounced();
    }

    function toggle(key: MultiFilterKey, value: string): void {
        state[key] = state[key].includes(value)
            ? state[key].filter((item) => item !== value)
            : [...state[key], value];
        visit();
    }

    function set<K extends keyof JobFilters>(
        key: K,
        value: JobFilters[K],
    ): void {
        state[key] = value;
        // Presets and custom ranges are mutually exclusive.
        if (key === 'posted' && value) {
            state.from = null;
            state.to = null;
        }
        if ((key === 'from' || key === 'to') && value) {
            state.posted = null;
        }
        visit();
    }

    function clear(): void {
        for (const key of MULTI_KEYS) state[key] = [];
        Object.assign(state, {
            q: null,
            posted: null,
            from: null,
            to: null,
            salary_min: null,
            currency: null,
            min_score: null,
        });
        visit();
    }

    /** Switching tabs resets the order to that tab's default. */
    function setTab(tab: JobFilters['tab']): void {
        state.tab = tab;
        state.ineligible = false;
        state.sort = defaultSort(state);
        visit();
    }

    const activeCount = computed(
        () =>
            MULTI_KEYS.reduce((total, key) => total + state[key].length, 0) +
            (state.posted || state.from || state.to ? 1 : 0) +
            (state.salary_min ? 1 : 0) +
            (state.min_score ? 1 : 0),
    );

    return {
        state,
        loading,
        setSearch,
        toggle,
        set,
        clear,
        setTab,
        goToPage: visit,
        activeCount,
    };
}
