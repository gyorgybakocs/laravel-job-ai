<script setup lang="ts">
import RepositoryLanguageFilter from '@/components/RepositoryLanguageFilter.vue';
import { Button } from '@/components/ui/button';
import { useRepositoryFilters } from '@/composables/useRepositoryFilters';
import type { LanguageOption, RepositoryFiltersData, RepositorySortValue, RepositoryStatus, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    filters: RepositoryFiltersData;
    sort: RepositorySortValue;
    languageOptions: LanguageOption[];
    statuses: RepositoryStatus[];
}>();
const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.repositories);
const { apply } = useRepositoryFilters(props);
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex gap-1">
            <Button
                v-for="status in statuses"
                :key="status"
                :variant="filters.status === status ? 'default' : 'outline'"
                :aria-pressed="filters.status === status"
                @click="apply({ status: filters.status === status ? null : status })"
            >
                {{ lang[`filter_${status}`] }}
            </Button>
        </div>
        <RepositoryLanguageFilter :options="languageOptions" :selected="filters.languages" @apply="apply({ languages: $event })" />
    </div>
</template>
