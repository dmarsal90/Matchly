<script setup lang="ts">
import { salaryParts } from '@/features/jobs/lib/format';
import type { JobSalary } from '@/features/jobs/types';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ salary: JobSalary }>();

const text = computed(() => {
    const parts = salaryParts(props.salary, getActiveLanguage());
    const amount =
        parts.kind === 'text'
            ? parts.text
            : trans(`jobs.salary.${parts.kind}`, {
                  min: parts.min ?? '',
                  max: parts.max ?? '',
              });

    return parts.period
        ? `${amount}${trans(`jobs.salary.per.${parts.period}`)}`
        : amount;
});
</script>

<template>
    <span v-if="text">{{ text }}</span>
</template>
