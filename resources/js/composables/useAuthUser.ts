import type { User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

/**
 * The signed-in user, for pages and layouts behind the `auth` middleware.
 * Throws if used on a guest page instead of silently rendering nothing.
 */
export function useAuthUser(): ComputedRef<User> {
    const page = usePage();

    return computed(() => {
        const user = page.props.auth.user;

        if (user === null) {
            throw new Error('useAuthUser() requires an authenticated page.');
        }

        return user;
    });
}
