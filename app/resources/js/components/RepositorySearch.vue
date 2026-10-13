<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useRepositoryFilters } from '@/composables/useRepositoryFilters';
import type { RepositoryFiltersData, RepositorySearchField, RepositorySortValue, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ filters: RepositoryFiltersData; sort: RepositorySortValue; fields: RepositorySearchField[] }>();
const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.repositories);
const search = ref(props.filters.search);
const { apply } = useRepositoryFilters(props);
watch(
    () => props.filters.search,
    (value) => {
        search.value = value;
    },
);
function submit() {
    apply({ search: search.value });
}
function selectField(value: string) {
    const field = props.fields.find((field) => field === value);
    if (field) apply({ search: search.value, search_in: field });
}
</script>

<template>
    <form class="flex min-w-0 items-center gap-1" role="search" @submit.prevent="submit">
        <Input
            v-model="search"
            type="search"
            maxlength="255"
            :placeholder="lang.search_placeholder"
            :aria-label="lang.search_placeholder"
            class="w-44 sm:w-52"
            @search="submit"
        />
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button type="button" variant="outline" :aria-label="lang.search_in"
                    >{{ lang[`search_${filters.search_in}`] }}<ChevronDown class="size-4" aria-hidden="true"
                /></Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuRadioGroup :model-value="filters.search_in" @update:model-value="selectField">
                    <DropdownMenuRadioItem v-for="field in fields" :key="field" :value="field">{{ lang[`search_${field}`] }}</DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    </form>
</template>
