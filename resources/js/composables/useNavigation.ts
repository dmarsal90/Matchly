import { index as applications } from '@/routes/applications';
import { edit as editCareer } from '@/routes/career';
import { index as jobs } from '@/routes/jobs';
import type { NavItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Briefcase, Shield, Star, UserRound } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

/** Navigation shared by the sidebar and header layouts. */
export function useNavigation(): {
    main: ComputedRef<NavItem[]>;
    secondary: ComputedRef<NavItem[]>;
} {
    const page = usePage();

    const main = computed<NavItem[]>(() => [
        { title: trans('nav.jobs'), href: jobs(), icon: Briefcase },
        { title: trans('nav.applications'), href: applications(), icon: Star },
        { title: trans('nav.profile'), href: editCareer(), icon: UserRound },
    ]);

    // Rendered as plain links: the admin panel is not an Inertia app.
    const secondary = computed<NavItem[]>(() =>
        page.props.auth.user?.is_admin
            ? [{ title: trans('nav.admin'), href: '/admin', icon: Shield }]
            : [],
    );

    return { main, secondary };
}
