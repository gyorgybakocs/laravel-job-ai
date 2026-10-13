import type { RepositoryFiltersData, RepositorySortValue } from '@/types';
import { router } from '@inertiajs/vue3';

export function useRepositoryFilters(props: { filters: RepositoryFiltersData; sort: RepositorySortValue }) {
    function apply(changes: Partial<RepositoryFiltersData> & { sort?: RepositorySortValue }) {
        const values = { ...props.filters, sort: props.sort, ...changes };
        router.get(
            route('repositories.index'),
            {
                ...values,
                status: values.status || undefined,
                search: values.search.trim() || undefined,
                languages: values.languages.length ? values.languages : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }
    return { apply };
}
