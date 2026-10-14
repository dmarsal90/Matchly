import { defineStore } from 'pinia';
import { ref } from 'vue';

/** UI-only state. Server data always comes from Inertia props, never from a store. */
export const useUiStore = defineStore('ui', () => {
    const mobileFiltersOpen = ref(false);

    function toggleMobileFilters(value?: boolean): void {
        mobileFiltersOpen.value = value ?? !mobileFiltersOpen.value;
    }

    return { mobileFiltersOpen, toggleMobileFilters };
});
