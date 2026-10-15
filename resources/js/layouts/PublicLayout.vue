<script setup lang="ts">
import SkipLink from '@/components/SkipLink.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <SkipLink />
        <PublicHeader />
        <main
            id="main-content"
            tabindex="-1"
            class="mx-auto w-full max-w-6xl flex-1 px-4 pb-10 sm:px-6"
        >
            <slot />
        </main>
        <PublicFooter />
    </div>
</template>
