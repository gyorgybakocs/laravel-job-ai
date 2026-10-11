<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.index);
</script>

<template>
    <Head :title="lang.title" />
    <AppLayout>
        <div class="flex flex-1 items-center justify-center px-4">
            <div class="w-full max-w-xl">
                <h1 class="mb-6 text-center text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ lang.title }}</h1>
                <p class="text-justify text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ lang.description1 }}</p>
                <p class="mt-4 text-justify text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ lang.description2 }}</p>
            </div>
        </div>
    </AppLayout>
</template>
