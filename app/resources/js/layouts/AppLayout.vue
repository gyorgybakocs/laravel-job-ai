<script setup lang="ts">
import AppNav from '@/components/AppNav.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gray-100 dark:bg-gray-900">
        <AppNav />
        <main class="flex flex-1 flex-col">
            <slot />
        </main>
    </div>
</template>
