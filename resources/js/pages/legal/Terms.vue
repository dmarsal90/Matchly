<script setup lang="ts">
import LegalPage from '@/components/LegalPage.vue';
</script>

<template>
    <LegalPage
        document="terms"
        :sections="['service', 'account', 'ai', 'applications']"
    />
</template>
