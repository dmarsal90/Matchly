<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import ApplyButton from '@/features/jobs/components/ApplyButton.vue';
import MatchScore from '@/features/jobs/components/MatchScore.vue';
import SalaryLabel from '@/features/jobs/components/SalaryLabel.vue';
import SaveJobButton from '@/features/jobs/components/SaveJobButton.vue';
import { relativeTime } from '@/features/jobs/lib/format';
import type { Job } from '@/features/jobs/types';
import { show } from '@/routes/jobs';
import { Link } from '@inertiajs/vue3';
import { Banknote, Clock, Eye, Globe2, MapPin } from '@lucide/vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{ job: Job }>();
const emit = defineEmits<{ tag: [value: string] }>();

const posted = computed(() =>
    relativeTime(props.job.posted_at, getActiveLanguage()),
);
const regionNames = computed(() => {
    const names = new Intl.DisplayNames([getActiveLanguage()], {
        type: 'region',
    });

    return props.job.allowed_regions
        .slice(0, 4)
        .map((code) =>
            code.length === 2
                ? (names.of(code) ?? code)
                : code.replace('_', ' '),
        )
        .join(', ');
});
const initial = computed(() =>
    props.job.company.trim().charAt(0).toUpperCase(),
);
// Third-party logos break often (hotlink protection, dead CDNs): fall back to the initial.
const logoFailed = ref(false);
</script>

<template>
    <article
        class="group relative flex flex-col gap-3 rounded-xl border bg-card p-4 transition-colors hover:border-foreground/20 sm:flex-row sm:gap-4"
        :class="job.viewed && 'bg-muted/30'"
    >
        <div
            class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted text-sm font-semibold text-muted-foreground"
            aria-hidden="true"
        >
            <img
                v-if="job.company_logo_url && !logoFailed"
                :src="job.company_logo_url"
                alt=""
                class="size-full object-contain"
                loading="lazy"
                referrerpolicy="no-referrer"
                @error="logoFailed = true"
            />
            <span v-else>{{ initial }}</span>
        </div>

        <div class="min-w-0 flex-1 space-y-2">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="leading-snug font-semibold">
                        <Link
                            :href="show({ job: job.id, slug: job.slug })"
                            class="after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:rounded-xl focus-visible:after:ring-2 focus-visible:after:ring-ring"
                        >
                            {{ job.title }}
                        </Link>
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        {{ job.company }}
                    </p>
                </div>
                <div class="flex shrink-0 items-start gap-2">
                    <Badge
                        v-if="job.viewed"
                        variant="outline"
                        class="gap-1 text-muted-foreground"
                    >
                        <Eye class="size-3" aria-hidden="true" />{{
                            trans('jobs.card.viewed')
                        }}
                    </Badge>
                    <MatchScore v-if="job.match" :match="job.match" />
                </div>
            </div>

            <div class="flex flex-wrap gap-1.5">
                <Badge variant="secondary">{{
                    trans(`jobs.work_mode.${job.work_mode}`)
                }}</Badge>
                <Badge v-if="job.employment_type" variant="outline">{{
                    trans(`jobs.type.${job.employment_type}`)
                }}</Badge>
                <Badge v-if="job.seniority" variant="outline">{{
                    trans(`jobs.seniority.${job.seniority}`)
                }}</Badge>
            </div>

            <dl
                class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
            >
                <div
                    v-if="job.remote_scope === 'worldwide'"
                    class="flex items-center gap-1"
                >
                    <dt class="sr-only">{{ trans('jobs.filters.scope') }}</dt>
                    <Globe2 class="size-3.5" aria-hidden="true" />
                    <dd>{{ trans('jobs.scope.worldwide') }}</dd>
                </div>
                <div
                    v-else-if="job.allowed_regions.length > 0"
                    class="flex items-center gap-1"
                >
                    <dt class="sr-only">{{ trans('jobs.filters.scope') }}</dt>
                    <MapPin class="size-3.5" aria-hidden="true" />
                    <dd>{{ regionNames }}</dd>
                </div>
                <div v-if="job.salary" class="flex items-center gap-1">
                    <dt class="sr-only">{{ trans('jobs.filters.salary') }}</dt>
                    <Banknote class="size-3.5" aria-hidden="true" />
                    <dd><SalaryLabel :salary="job.salary" /></dd>
                </div>
                <div class="flex items-center gap-1">
                    <dt class="sr-only">{{ trans('jobs.filters.posted') }}</dt>
                    <Clock class="size-3.5" aria-hidden="true" />
                    <dd>
                        <time :datetime="job.posted_at">{{ posted }}</time>
                    </dd>
                </div>
            </dl>

            <ul
                v-if="job.tags.length > 0"
                class="relative z-10 flex flex-wrap gap-1.5"
            >
                <li v-for="tag in job.tags" :key="tag">
                    <button
                        type="button"
                        class="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="emit('tag', tag)"
                    >
                        {{ tag }}
                    </button>
                </li>
            </ul>
        </div>

        <div
            class="relative z-10 flex items-center justify-between gap-2 sm:flex-col sm:items-end sm:justify-between"
        >
            <SaveJobButton
                :job-id="job.id"
                :tracking-status="job.tracking_status"
            />
            <div class="flex flex-col items-end gap-1">
                <ApplyButton :href="job.apply_url" />
                <a
                    :href="job.source.url"
                    target="_blank"
                    rel="noopener"
                    class="text-xs text-muted-foreground hover:underline"
                >
                    {{ trans('jobs.card.via', { source: job.source.name }) }}
                </a>
            </div>
        </div>
    </article>
</template>
