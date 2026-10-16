import { index as alerts } from '@/routes/alerts';
import { index as applications } from '@/routes/applications';
import { edit as editCareer } from '@/routes/career';
import { index as insights } from '@/routes/insights';
import { index as jobs } from '@/routes/jobs';
import { index as market } from '@/routes/market';
import { index as skills } from '@/routes/skills';
import type { NavItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import {
    BellRing,
    Briefcase,
    ChartNoAxesColumn,
    Shield,
    Sparkles,
    Star,
    TrendingUp,
    UserRound,
} from '@lucide/vue';
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
        {
            title: trans('nav.insights'),
            href: insights(),
            icon: ChartNoAxesColumn,
        },
        { title: trans('nav.market'), href: market(), icon: TrendingUp },
        { title: trans('nav.alerts'), href: alerts(), icon: BellRing },
        { title: trans('nav.profile'), href: editCareer(), icon: UserRound },
        { title: trans('nav.skills'), href: skills(), icon: Sparkles },
    ]);

    // Rendered as plain links: the admin panel is not an Inertia app.
    const secondary = computed<NavItem[]>(() =>
        page.props.auth.user?.is_admin
            ? [{ title: trans('nav.admin'), href: '/admin', icon: Shield }]
            : [],
    );

    return { main, secondary };
}
