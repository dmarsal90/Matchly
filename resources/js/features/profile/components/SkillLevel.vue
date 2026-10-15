<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';

defineProps<{ level: number | null }>();
</script>

<template>
    <span
        v-if="level"
        class="inline-flex items-center gap-0.5"
        role="img"
        :aria-label="
            trans('skills.profile.level_value', { level: String(level) })
        "
    >
        <span
            v-for="dot in 5"
            :key="dot"
            class="size-2 rounded-full"
            :class="dot <= level ? 'bg-primary' : 'bg-muted'"
        />
    </span>
    <span v-else class="text-xs text-muted-foreground">{{
        trans('skills.profile.no_level')
    }}</span>
</template>
