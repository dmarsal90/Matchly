<script setup lang="ts">
import LegalPage from '@/components/LegalPage.vue';

defineProps<{ retention: { days: string; months: string } }>();
</script>

<template>
    <LegalPage
        document="privacy"
        :sections="[
            'controller',
            'data',
            'purposes',
            'security',
            'retention',
            'rights',
            'sources',
        ]"
        :replacements="retention"
    />
</template>
