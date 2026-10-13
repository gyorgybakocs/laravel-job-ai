<script setup lang="ts">
import RepositoryFilters from '@/components/RepositoryFilters.vue';
import RepositoryList from '@/components/RepositoryList.vue';
import RepositorySearch from '@/components/RepositorySearch.vue';
import RepositorySort from '@/components/RepositorySort.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    LanguageOption,
    Paginator,
    RepositoryFilterOptions,
    RepositoryFiltersData,
    RepositorySortValue,
    RepositoryWithTarget,
    SharedData,
} from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    sort: RepositorySortValue;
    filters: RepositoryFiltersData;
    filterOptions: RepositoryFilterOptions;
    languageOptions: LanguageOption[];
    repositories: Paginator<RepositoryWithTarget>;
}>();
const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.repositories);
</script>

<template>
    <Head :title="lang.page_title" />
    <AppLayout>
        <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <h1 class="mb-6 text-lg font-semibold">{{ lang.page_title }}</h1>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    <RepositoryFilters :filters="filters" :sort="sort" :language-options="languageOptions" :statuses="filterOptions.statuses" />
                    <RepositorySearch :filters="filters" :sort="sort" :fields="filterOptions.searchFields" />
                </div>
                <RepositorySort :filters="filters" :sort="sort" :options="filterOptions.sorts" />
            </div>
            <RepositoryList
                :repositories="repositories"
                :empty-message="filters.status || filters.languages.length || filters.search ? lang.empty_filtered : lang.empty"
            />
        </div>
    </AppLayout>
</template>
