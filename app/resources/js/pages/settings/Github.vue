<script setup lang="ts">
import GithubCredentialsForm from '@/components/GithubCredentialsForm.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ githubUsername: string | null; status?: string | null }>();
const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.github);
</script>

<template>
    <Head :title="lang.page_title" />
    <AppLayout>
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="lang.page_title" :description="lang.description" />
                <GithubCredentialsForm :github-username="githubUsername" :status="status" />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
