<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useRepositoryFilters } from '@/composables/useRepositoryFilters';
import type { RepositoryFiltersData, RepositorySortValue, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ filters: RepositoryFiltersData; sort: RepositorySortValue; options: RepositorySortValue[] }>();
const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.repositories);
const { apply } = useRepositoryFilters(props);
function selectSort(value: string) {
    const sort = props.options.find((option) => option === value);
    if (sort) apply({ sort });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" :aria-label="lang.sort_label"
                >{{ lang[`sort_${sort}`] }}<ChevronDown class="size-4" aria-hidden="true"
            /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuRadioGroup :model-value="sort" @update:model-value="selectSort">
                <DropdownMenuRadioItem v-for="option in options" :key="option" :value="option">{{ lang[`sort_${option}`] }}</DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
