<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Card, CardContent } from '@/components/ui/card';
import { formatDate } from '@/composables/useDate';
import type { Paginator, RepositoryWithTarget, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ repositories: Paginator<RepositoryWithTarget>; emptyMessage: string }>();
const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.repositories);
const emptyValue = computed(() => page.props.lang.targets.unknown_value);
</script>

<template>
    <Card>
        <CardContent class="p-0">
            <p v-if="!repositories.data.length" class="p-6 text-sm text-muted-foreground">{{ emptyMessage }}</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="px-4 py-3">{{ lang.col_name }}</th>
                            <th class="px-4 py-3">{{ lang.col_target }}</th>
                            <th class="px-4 py-3">{{ lang.col_description }}</th>
                            <th class="px-4 py-3">{{ lang.col_language }}</th>
                            <th class="px-4 py-3">{{ lang.col_stars }}</th>
                            <th class="px-4 py-3">{{ lang.col_open_issues }}</th>
                            <th class="px-4 py-3">{{ lang.col_archived }}</th>
                            <th class="px-4 py-3">{{ lang.col_updated }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="repository in repositories.data" :key="repository.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <a
                                    :href="repository.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-primary hover:underline"
                                    >{{ repository.full_name }}</a
                                >
                            </td>
                            <td class="px-4 py-3">{{ repository.target.name }}</td>
                            <td class="max-w-md px-4 py-3">{{ repository.description ?? emptyValue }}</td>
                            <td class="px-4 py-3">{{ repository.language ?? emptyValue }}</td>
                            <td class="px-4 py-3">{{ repository.stars }}</td>
                            <td class="px-4 py-3">{{ repository.open_issues }}</td>
                            <td class="px-4 py-3">{{ repository.archived ? lang.archived_yes : lang.archived_no }}</td>
                            <td class="px-4 py-3">{{ formatDate(repository.github_updated_at, emptyValue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
    <Pagination :links="repositories.links" />
</template>
